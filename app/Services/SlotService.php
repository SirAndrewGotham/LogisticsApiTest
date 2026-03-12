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
         * Retrieve current availability for all slots using a short randomized cache and stampede protection.
         *
         * The result is cached for a random duration between the configured minimum and maximum TTL (5–15 seconds).
         * When the cache is computed, a lock is used to prevent cache stampedes and a brief retry is performed if the lock
         * cannot be acquired.
         *
         * @return array An array of slot availability entries. Each entry is an associative array with keys:
         *               - `slot_id` (int): the slot identifier
         *               - `capacity` (int): the slot's total capacity
         *               - `remaining` (int): the slot's remaining capacity
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
     * Build an array of slot availability records from persisted Slot models.
     *
     * @return array[] Array of associative arrays, each with keys:
     *                 - `slot_id` (int): the Slot id,
     *                 - `capacity` (int): the Slot capacity,
     *                 - `remaining` (int): the Slot remaining capacity.
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
     * Create a new hold for a slot while enforcing idempotency and capacity constraints.
     *
     * Checks for an existing idempotency response and returns its hold if present.
     * Otherwise, locks the slot, verifies remaining capacity, creates a held Hold
     * that expires after the configured hold duration, decrements slot capacity,
     * stores the idempotency response, and invalidates the availability cache.
     *
     * @param int $slotId The identifier of the slot to place a hold on.
     * @param string|null $idempotencyKey A UUID idempotency key required to make the request idempotent.
     * @return \App\Models\Hold The created or existing held Hold associated with the idempotency key.
     * @throws \InvalidArgumentException If the idempotency key is missing or not a valid UUID.
     * @throws \App\Exceptions\SlotCapacityException If the slot has no remaining capacity.
     * @throws \RuntimeException If decrementing the slot capacity fails.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the specified slot (or referenced hold) cannot be found.
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
         * Confirm a held reservation and persist its confirmed status.
         *
         * Runs inside a database transaction and acquires row-level locks on the hold and its associated slot.
         * Verifies the hold is currently in the 'held' state and not expired, marks it as 'confirmed', and
         * invalidates the availability cache. If the hold is not in the 'held' state or has expired, the
         * operation aborts with HTTP 409; expired holds are marked as expired before aborting.
         *
         * @param int $holdId The identifier of the hold to confirm.
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
     * Cancel a held hold and restore slot capacity when applicable.
     *
     * Marks the specified hold as cancelled. If the hold has not expired, increments the associated
     * slot's remaining capacity. The operation runs inside a database transaction and invalidates
     * the availability cache.
     *
     * @param int $holdId The ID of the hold to cancel.
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
         * Invalidate the availability cache and release any associated computation lock.
         *
         * Removes the cached availability payload and forcefully releases the lock used to coordinate cache recomputation.
         */
    public function invalidateAvailabilityCache(): void
    {
        Cache::forget(self::AVAILABILITY_CACHE_KEY);

        // Also clear any lock that might exist
        Cache::lock(self::AVAILABILITY_CACHE_KEY . '.lock')->forceRelease();
    }

    /**
         * Retrieve a previously stored idempotency response for the given idempotency key.
         *
         * @param string $idempotencyKey The UUID idempotency key used to lookup a stored response.
         * @return array|null An associative array with keys `hold_id` (int) and `stored_at` (Carbon) if present, `null` if no response is stored.
         */
    private function getIdempotencyResponse(string $idempotencyKey): ?array
    {
        return Cache::get(self::IDEMPOTENCY_CACHE_PREFIX . $idempotencyKey);
    }

    /**
     * Store an idempotency mapping from the provided key to a hold ID in the cache using the service's idempotency prefix and TTL.
     *
     * The cached value is an array with keys `hold_id` (the stored hold ID) and `stored_at` (timestamp when stored).
     *
     * @param string $idempotencyKey The idempotency key to store (prefixed internally before caching).
     * @param int $holdId The ID of the hold to associate with the idempotency key.
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
     * Marks all holds that have passed their expiry as expired, restores their slots' remaining capacity, and invalidates availability cache when any were processed.
     *
     * Runs the work inside a database transaction to ensure atomicity.
     *
     * @return int The number of expired holds that were processed.
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
