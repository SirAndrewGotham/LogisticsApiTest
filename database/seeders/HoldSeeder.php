<?php

namespace Database\Seeders;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HoldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing holds
        Hold::query()->delete();

        // Get available slots
        $slot1 = Slot::find(1); // Slot with 10 capacity, 10 remaining
        $slot3 = Slot::find(3); // Slot with 8 capacity, 3 remaining

        if (!$slot1 || !$slot3) {
            $this->command->error('Required slots not found. Run SlotSeeder first.');
            return;
        }

        $holds = [
            // Active holds (not expired)
            [
                'slot_id' => $slot1->id,
                'user_id' => 1001,
                'status' => 'held',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->addMinutes(10),
            ],
            [
                'slot_id' => $slot1->id,
                'user_id' => 1002,
                'status' => 'held',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->addMinutes(15),
            ],

            // Confirmed hold
            [
                'slot_id' => $slot3->id,
                'user_id' => 1003,
                'status' => 'confirmed',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->addMinutes(30),
            ],

            // Cancelled hold (for history)
            [
                'slot_id' => $slot3->id,
                'user_id' => 1004,
                'status' => 'cancelled',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->subMinutes(5),
            ],

            // Expired hold (should be cleaned up)
            [
                'slot_id' => $slot1->id,
                'user_id' => 1005,
                'status' => 'held',
                'idempotency_key' => Str::uuid()->toString(),
                'expires_at' => now()->subMinutes(1),
            ],
        ];

        foreach ($holds as $holdData) {
            Hold::create($holdData);

            // Update slot remaining counts for active/confirmed holds
            if (in_array($holdData['status'], ['held', 'confirmed'])) {
                $slot = Slot::find($holdData['slot_id']);
                if ($slot && $slot->remaining > 0) {
                    $slot->decrement('remaining');
                }
            }
        }

        $this->command->info('✅ Holds seeded successfully!');

        // Display summary - FIXED: Handle string dates safely
        $this->command->table(
            ['ID', 'Slot ID', 'User ID', 'Status', 'Expires At', 'Age'],
            Hold::all()->map(function ($hold) {
                // Convert expires_at to Carbon if it's a string
                $expiresAt = $hold->expires_at instanceof Carbon
                    ? $hold->expires_at
                    : Carbon::parse($hold->expires_at);

                return [
                    $hold->id,
                    $hold->slot_id,
                    $hold->user_id,
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
