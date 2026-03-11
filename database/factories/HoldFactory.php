<?php

namespace Database\Factories;

use App\Models\Hold;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hold>
 */
class HoldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slot_id' => \App\Models\Slot::factory(),
            'user_id' => $this->faker->numberBetween(1000, 9999),
            'status' => 'held',
            'idempotency_key' => Str::uuid()->toString(),
            'expires_at' => now()->addMinutes(5),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
