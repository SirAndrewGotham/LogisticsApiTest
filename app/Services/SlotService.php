<?php

namespace App\Services;

use App\Exceptions\SlotCapacityException;
use App\Exceptions\HoldNotConfirmableException;
use App\Exceptions\HoldExpiredException;
use App\Models\Slot;
use App\Models\Hold;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlotService
{
    private const AVAILABILITY_CACHE_KEY = 'slots.availability';
    private const CACHE_TTL_SECONDS_MIN = 5;
    private const CACHE_TTL_SECONDS_MAX = 15;
    private const HOLD_EXPIRY_MINUTES = 5;
    private const string IDEMPOTENCY_CACHE_PREFIX = 'idempotency.'; // FIXED: Must match middleware
    private const IDEMPOTENCY_TTL_HOURS = 24;

    /**
     * Get available slots with cache stampede protection
     */
    public function getAvailableSlots(): array
    {
        // Random TTL between 5-15 seconds as specified
        $ttl = rand(self::CACHE_TTL_SECONDS_MIN, self::CACHE_TTL_SECONDS_MAX);

        $cacheKey = self::AVAILABILITY_CACHE_KEY;

        // Try to get from cache first (fast path)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Use atomic lock to prevent cache stampede
        $result = Cache::lock($cacheKey . '.lock', 3)
            ->block(1, function () use ($cacheKey, $ttl) {
                // Double-check cache inside the lock
                if ($cached = Cache::get($cacheKey)) {
                    return $cached;
                }

                // We hold the lock, so we're responsible for calculating
                $result = $this->calculateAvailability();
                Cache::put($cacheKey, $result, $ttl);

                return $result;
            });

        return $result ?? [];
    }

    /**
     * Calculate slot availability from database
     */
    private function calculateAvailability(): array
    {
        return Slot::query()
            ->select(['id', 'capacity', 'remaining'])
            ->orderBy('id')
            ->get()
            ->map(function (Slot $slot) {
                return [
                    'slot_id' => $slot->id,
                    'capacity' => $slot->capacity,
                    'remaining' => $slot->remaining,
                ];
            })
            ->toArray();
    }

    /**
     * Create a hold with idempotency and capacity checks
     */
    public function createHold(int $slotId, string $idempotencyKey): Hold
    {
        // Note: Middleware already validated the key, but double-check for safety
        if (!Str::isUuid($idempotencyKey)) {
            throw new \InvalidArgumentException('Valid Idempotency-Key header (UUID) is required');
        }

        // Check idempotency first
        $idempotencyResponse = $this->getIdempotencyResponse($idempotencyKey);
        if ($idempotencyResponse) {
            return Hold::findOrFail($idempotencyResponse['hold_id']);
        }

        return DB::transaction(function () use ($slotId, $idempotencyKey) {
            // Lock the slot for update to prevent race conditions
            $slot = Slot::where('id', $slotId)->lockForUpdate()->firstOrFail();

            // Check capacity
            if ($slot->remaining <= 0) {
                throw new SlotCapacityException('No capacity available in this slot', $slotId);
            }

            // Check for existing active holds with same idempotency key
            $existingHold = Hold::where('idempotency_key', $idempotencyKey)
                ->where('status', 'held')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingHold) {
                return $existingHold;
            }

            // Create the hold
            $hold = Hold::create([
                'slot_id' => $slotId,
                'status' => 'held',
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes(self::HOLD_EXPIRY_MINUTES),
            ]);

            // Atomically decrement remaining capacity
            if (!$slot->decrementRemaining()) {
                throw new \RuntimeException('Failed to decrement slot capacity');
            }

            // Store idempotency response
            $this->storeIdempotencyResponse($idempotencyKey, $hold->id);

            // Invalidate availability cache
            $this->invalidateAvailabilityCache();

            return $hold->fresh();
        });
    }

    /**
     * Confirm a hold with atomic operations
     */
    public function confirmHold(int $holdId): void
    {
        DB::transaction(function () use ($holdId) {
            // Lock the hold and its slot for update
            $hold = Hold::with(['slot' => function ($query) {
                $query->lockForUpdate();
            }])->where('id', $holdId)->lockForUpdate()->firstOrFail();

            // Validate hold state
            if ($hold->status !== 'held') {
                throw new HoldNotConfirmableException('Hold is not in a confirmable state');
            }

            if ($hold->isExpired()) {
                // Mark as expired outside transaction to persist
                DB::afterCommit(function () use ($hold) {
                    $hold->markAsExpired();
                });
                throw new HoldExpiredException('Hold has expired');
            }

            // Confirm the hold
            $hold->markAsConfirmed();

            // Invalidate cache
            $this->invalidateAvailabilityCache();
        });
    }

    /**
     * Cancel a hold and return capacity
     */
    public function cancelHold(int $holdId): void
    {
        DB::transaction(function () use ($holdId) {
            // Lock the hold and its slot
            $hold = Hold::with(['slot' => function ($query) {
                $query->lockForUpdate();
            }])->where('id', $holdId)->lockForUpdate()->firstOrFail();

            // Only allow cancelling held holds
            if ($hold->status !== 'held') {
                throw new HoldNotConfirmableException('Only held holds can be cancelled');
            }

            // Mark as cancelled
            $hold->markAsCancelled();

            // Return capacity if hold hasn't expired
            if (!$hold->isExpired()) {
                if (!$hold->slot->incrementRemaining()) {
                    throw new \RuntimeException('Failed to increment slot capacity');
                }
            }

            // Invalidate cache
            $this->invalidateAvailabilityCache();
        });
    }

    /**
     * Invalidate availability cache
     */
    public function invalidateAvailabilityCache(): void
    {
        Cache::forget(self::AVAILABILITY_CACHE_KEY);
        Cache::lock(self::AVAILABILITY_CACHE_KEY . '.lock')->forceRelease();
    }

    /**
     * Get stored idempotency response
     */
    private function getIdempotencyResponse(string $idempotencyKey): ?array
    {
        return Cache::get(self::IDEMPOTENCY_CACHE_PREFIX . $idempotencyKey);
    }

    /**
     * Store idempotency response
     */
    private function storeIdempotencyResponse(string $idempotencyKey, int $holdId): void
    {
        Cache::put(
            self::IDEMPOTENCY_CACHE_PREFIX . $idempotencyKey,
            ['hold_id' => $holdId, 'stored_at' => now()],
            now()->addHours(self::IDEMPOTENCY_TTL_HOURS)
        );
    }

    /**
     * Clean up expired holds (can be called from a scheduled job)
     */
    public function cleanupExpiredHolds(): int
    {
        return DB::transaction(function () {
            // Make sure Hold model has scopeExpired() method
            $expiredHolds = Hold::expired()->lockForUpdate()->get();
            $count = 0;

            foreach ($expiredHolds as $hold) {
                $hold->markAsExpired();
                if (!$hold->slot->incrementRemaining()) {
                    throw new \RuntimeException("Failed to increment capacity for slot {$hold->slot_id}");
                }
                $count++;
            }

            if ($count > 0) {
                $this->invalidateAvailabilityCache();
            }

            return $count;
        });
    }
}
