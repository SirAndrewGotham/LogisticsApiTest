<?php

namespace Database\Seeders;

use App\Models\Slot;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    /**
     * Seed the slots table with predefined sample records and display a summary table.
     *
     * Deletes existing Slot records, inserts five sample slots with varying capacity and remaining values,
     * then outputs a success message and a table listing ID, Capacity, Remaining, and Status
     * (Status is "Available" when remaining > 0, otherwise "Full").
     */
    public function run(): void
    {
        // Clear existing slots (optional, but good for testing)
        Slot::query()->delete();

        // Create sample slots with different scenarios
        $slots = [
            [
                'id' => 1,
                'capacity' => 10,
                'remaining' => 10,
            ],
            [
                'id' => 2,
                'capacity' => 5,
                'remaining' => 5,
            ],
            [
                'id' => 3,
                'capacity' => 8,
                'remaining' => 3, // Partially booked
            ],
            [
                'id' => 4,
                'capacity' => 3,
                'remaining' => 0, // Fully booked
            ],
            [
                'id' => 5,
                'capacity' => 15,
                'remaining' => 15, // Large capacity slot
            ],
        ];

        foreach ($slots as $slotData) {
            Slot::create($slotData);
        }

        $this->command->info('✅ Slots seeded successfully!');
        $this->command->table(
            ['ID', 'Capacity', 'Remaining', 'Status'],
            Slot::all()->map(function ($slot) {
                return [
                    $slot->id,
                    $slot->capacity,
                    $slot->remaining,
                    $slot->remaining > 0 ? 'Available' : 'Full',
                ];
            })->toArray()
        );
    }
}
