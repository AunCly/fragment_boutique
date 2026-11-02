<?php

namespace Database\Factories;

use App\Models\Palette;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Palette>
 */
class PaletteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
