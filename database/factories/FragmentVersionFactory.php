<?php

namespace Database\Factories;

use App\Models\FragmentProject;
use App\Models\FragmentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The grid is a placeholder: pass one built from the project palette.
 *
 * @extends Factory<FragmentVersion>
 */
class FragmentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => FragmentProject::factory(),
            'number' => fn (array $attributes) => FragmentProject::find($attributes['project_id'])?->nextVersionNumber() ?? 1,
            'kind' => FragmentVersion::KIND_GENERATION,
            'style' => 'base',
            'grid' => [[1, 1], [1, 1]],
            'background_id' => fn (array $attributes) => FragmentProject::find($attributes['project_id'])?->background_id,
            'score' => $this->faker->randomFloat(1, 0, 10),
            'problems' => [],
            'is_preferred' => false,
        ];
    }
}
