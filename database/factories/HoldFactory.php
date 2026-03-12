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
    /**
     * Define the default attributes for a Hold model factory.
     *
     * The returned array includes:
     * - 'slot_id': a Slot model instance created by the Slot factory.
     * - 'status': the string 'held'.
     * - 'idempotency_key': a UUID string used for idempotency.
     * - 'expires_at': a timestamp set five minutes from now.
     *
     * @return array The default attributes for creating a Hold.
     */
    public function definition(): array
    {
        return [
            'slot_id' => \App\Models\Slot::factory(),
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
