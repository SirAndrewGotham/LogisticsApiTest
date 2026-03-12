<?php

namespace App\Services;

use App\Exceptions\SlotCapacityException;
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
    private const IDEMPOTENCY_CACHE_PREFIX = 'idempotency.';
    private const IDEMPOTENCY_TTL_HOURS = 24;

    /**
     * Get available slots with cache stampede protection
     */
    public function getAvailableSlots(): array
    {
        // Random TTL between 5-15 seconds as specified
        $ttl = rand(self::CACHE_TTL_SECONDS_MIN, self::CACHE_TTL_SECONDS_MAX);

        return Cache::remember(
            self::AVAILABILITY_CACHE_KEY,
            $ttl,
            function () {
                // Cache stampede protection: lock while calculating
                $lock = Cache::lock(self::AVAILABILITY_CACHE_KEY . '.lock', 3);

                try {
                    if ($lock->get()) {
                        return $this->calculateAvailability();
                    }

                    // If can't get lock, wait and retry once
                    usleep(100000); // 100ms
                    return Cache::get(self::AVAILABILITY_CACHE_KEY) ?? $this->calculateAvailability();
                } finally {
                    optional($lock)->release();
                }
            }
        );
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
    public function createHold(int $slotId, ?string $idempotencyKey): Hold
    {
        // Note: UUID format validation is done in middleware
        // But we still need to ensure a key is provided
        if (!$idempotencyKey) {
            throw new \InvalidArgumentException('Idempotency-Key header is required');
        }

        // Optional: Validate it's a UUID (redundant but safe)
        // Middleware already did this, but good for service-level integrity
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
                throw new SlotCapacityException();
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
            $decremented = $slot->decrementRemaining();

            if (!$decremented) {
                // This should rarely happen due to lock, but handle it
                throw new \RuntimeException('Failed to decrement slot capacity');
            }

            // Store idempotency response
            $this->storeIdempotencyResponse($idempotencyKey, $hold->id);

            // Invalidate availability cache
            $this->invalidateAvailabilityCache();

            return $hold->fresh(); // Return fresh instance with updated relations
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
                abort(409, 'Hold is not in a confirmable state');
            }

            if ($hold->isExpired()) {
                $hold->markAsExpired();
                abort(409, 'Hold has expired');
            }

            // Confirm the hold
            $hold->markAsConfirmed();

            // Note: Capacity was already decremented when hold was created
            // No need to decrement again, just ensure it's not negative

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
                abort(409, 'Only held holds can be cancelled');
            }

            // Mark as cancelled
            $hold->markAsCancelled();

            // Return capacity if hold hasn't expired
            if (!$hold->isExpired()) {
                $hold->slot->incrementRemaining();
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

        // Also clear any lock that might exist
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
            $expiredHolds = Hold::expired()->lockForUpdate()->get();
            $count = 0;

            foreach ($expiredHolds as $hold) {
                $hold->markAsExpired();
                $hold->slot->incrementRemaining();
                $count++;
            }

            if ($count > 0) {
                $this->invalidateAvailabilityCache();
            }

            return $count;
        });
    }
}
