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
     * Provide default attribute values for creating a Hold model instance.
     *
     * @return array<string,mixed> Attribute array for a Hold model:
     *  - `slot_id`: a new Slot factory instance for the related slot.
     *  - `user_id`: integer user identifier between 1000 and 9999.
     *  - `status`: the hold status (`'held'`).
     *  - `idempotency_key`: UUID string used for idempotency.
     *  - `expires_at`: Date/time value set to five minutes from now.
     */
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

    /**
     * Configure the factory to produce Hold models with a status of 'confirmed'.
     *
     * @return static The factory instance with the 'confirmed' status state applied.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Configure the factory to produce a Hold with status "cancelled".
     *
     * @return static The factory instance with `'status' => 'cancelled'`.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Set the factory state so the hold's `expires_at` timestamp is one minute in the past.
     *
     * @return static The factory instance with `expires_at` set to one minute before the current time.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
