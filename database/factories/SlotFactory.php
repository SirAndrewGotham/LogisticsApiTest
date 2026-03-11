<?php

namespace Database\Factories;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slot>
 */
class SlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'capacity' => $this->faker->numberBetween(1, 20),
            'remaining' => function (array $attributes) {
                return $this->faker->numberBetween(0, $attributes['capacity']);
            },
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining' => $this->faker->numberBetween(1, $attributes['capacity']),
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining' => 0,
        ]);
    }
}
