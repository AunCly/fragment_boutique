<?php

namespace Database\Factories;

use App\Models\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Format>
 */
class FormatFactory extends Factory
{
    public function definition(): array
    {
        $cols = $this->faker->randomElement([16, 24, 32, 48, 64, 96]);
        $rows = $this->faker->randomElement([16, 24, 32, 48, 64, 96]);

        return [
            'slug' => $this->faker->unique()->slug(2),
            'label' => "{$cols} × {$rows} cm",
            'cols' => $cols,
            'rows' => $rows,
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
            'is_tool_enabled' => false,
        ];
    }

    public function forTool(): static
    {
        return $this->state(['is_tool_enabled' => true]);
    }
}
