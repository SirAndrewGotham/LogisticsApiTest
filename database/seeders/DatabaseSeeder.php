<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run seeders for slots and holds and print a concise console summary for testers.
     *
     * Executes SlotSeeder and HoldSeeder, then writes informational lines to the console describing the seeded test data (slots and holds) and indicating readiness for API testing.
     */
    public function run(): void
    {
        $this->call([
            SlotSeeder::class,
            HoldSeeder::class,
        ]);

        $this->command->info('==========================================');
        $this->command->info('🎉 Database seeding completed!');
        $this->command->info('==========================================');
        $this->command->info('');
        $this->command->info('Test data includes:');
        $this->command->info('• 5 slots with various capacities');
        $this->command->info('• Active, confirmed, cancelled, and expired holds');
        $this->command->info('• Realistic remaining slot counts');
        $this->command->info('');
        $this->command->info('Ready for API testing! 🚀');
    }
}
