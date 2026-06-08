<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence(6, true).'?',
            'answer' => $this->faker->paragraph(3),
            'position' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
