<?php

namespace Database\Seeders;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HoldSeeder extends Seeder
{
    /**
     * Seed initial Hold records, adjust related Slot remaining capacities, and display summary tables.
     *
     * Seeds a predefined set of holds (active, confirmed, cancelled, and expired), decrements slot
     * remaining capacity for active holds within a transaction, and prints a table of created holds
     * and updated slot statuses to the console.
     *
     * @throws \RuntimeException If the required seed slots are not found (instructing to run SlotSeeder first).
     * @throws \RuntimeException If a referenced Slot is missing during hold creation.
     * @throws \RuntimeException If creating an active hold would exceed a Slot's remaining capacity.
     */
    public function run(): void
    {
        // Clear existing holds
        Hold::query()->delete();

        // Get available slots - find by deterministic attributes instead of hard-coded IDs
        $slot1 = Slot::where('capacity', 10)->where('remaining', 10)->first();
        $slot3 = Slot::where('capacity', 8)->where('remaining', 3)->first();

        if (!$slot1 || !$slot3) {
            throw new \RuntimeException('Required slots not found. Please run SlotSeeder first to create slots with capacity 10/remaining 10 and capacity 8/remaining 3.');
        }

        $holds = [
            // Active holds (not expired)
            [
                'slot_id' => $slot1->id,
                'status' => 'held',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->addMinutes(10),
            ],
            [
                'slot_id' => $slot1->id,
                'status' => 'held',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->addMinutes(15),
            ],

            // Confirmed hold
            [
                'slot_id' => $slot3->id,
                'status' => 'confirmed',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->addMinutes(30),
            ],

            // Cancelled hold (for history)
            [
                'slot_id' => $slot3->id,
                'status' => 'cancelled',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->subMinutes(5),
            ],

            // Expired hold (should be cleaned up)
            [
                'slot_id' => $slot1->id,
                'status' => 'held',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->subMinutes(1),
            ],
        ];

        DB::transaction(function () use ($holds) {
            foreach ($holds as $holdData) {
                $expiresAt = Carbon::parse($holdData['expires_at']);
                $isActive = in_array($holdData['status'], ['held', 'confirmed']) && $expiresAt->isFuture();

                $slot = Slot::lockForUpdate()->find($holdData['slot_id']); // Lock the row!

                if (!$slot) {
                    throw new \RuntimeException("Slot ID {$holdData['slot_id']} not found");
                }

                if ($isActive) {
                    if (!$slot->decrementRemaining()) {
                        throw new \RuntimeException("Cannot create active hold: Slot {$slot->id} has no remaining capacity");
                    }
                }

                Hold::create($holdData);
            }
        });

        $this->command->info('✅ Holds seeded successfully!');

        // Display summary - FIXED: Handle string dates safely
        $this->command->table(
            ['ID', 'Slot ID', 'Status', 'Expires At', 'Age'],
            Hold::all()->map(function ($hold) {
                // Convert expires_at to Carbon if it's a string
                $expiresAt = $hold->expires_at instanceof Carbon
                    ? $hold->expires_at
                    : Carbon::parse($hold->expires_at);

                return [
                    $hold->id,
                    $hold->slot_id,
                    $hold->status,
                    $expiresAt->format('H:i:s'),
                    $expiresAt->diffForHumans(),
                ];
            })->toArray()
        );

        // Show updated slot status
        $this->command->info('📊 Updated slot status:');
        $this->command->table(
            ['ID', 'Capacity', 'Remaining', 'Booked', 'Available'],
            Slot::all()->map(function ($slot) {
                return [
                    $slot->id,
                    $slot->capacity,
                    $slot->remaining,
                    $slot->capacity - $slot->remaining,
                    $slot->remaining > 0 ? '✅ Yes' : '❌ No',
                ];
            })->toArray()
        );
    }
}
