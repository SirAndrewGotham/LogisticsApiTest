<?php

namespace Database\Factories;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slot>
 */
class SlotFactory extends Factory
{
    /**
     * Define the default attributes for a Slot factory.
     *
     * capacity will be an integer between 1 and 20; remaining will be an integer between 0 and the chosen capacity.
     *
     * @return array<string,mixed> The default attribute values for the Slot model.
     */
    public function definition(): array
    {
        return [
            'capacity' => $this->faker->numberBetween(1, 20),
            'remaining' => function (array $attributes) {
                return $this->faker->numberBetween(0, $attributes['capacity']);
            },
        ];
    }

    /**
     * Create a factory state where the slot has at least one remaining capacity.
     *
     * @return static A factory instance with `remaining` set to an integer between 1 and the current `capacity`.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining' => $this->faker->numberBetween(1, $attributes['capacity']),
        ]);
    }

    /**
     * Create a factory state where the slot is full (no remaining capacity).
     *
     * @return static A factory instance configured with `remaining` set to 0.
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining' => 0,
        ]);
    }
}
