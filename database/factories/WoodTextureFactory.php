<?php

namespace Database\Factories;

use App\Models\WoodTexture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WoodTexture>
 */
class WoodTextureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => ucfirst($this->faker->unique()->word()),
            'hex' => $this->faker->hexColor(),
            'texture_path' => 'wood_textures/'.$this->faker->uuid().'.jpg',
            'tags' => ['Bois français', 'Couleur naturelle'],
            'grid_code' => strtoupper($this->faker->unique()->lexify('??')),
            'sort_order' => $this->faker->numberBetween(0, 30),
            'is_active' => true,
        ];
    }
}
