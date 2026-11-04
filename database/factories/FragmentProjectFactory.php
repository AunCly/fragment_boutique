<?php

namespace Database\Factories;

use App\Models\Format;
use App\Models\FragmentProject;
use App\Models\Palette;
use App\Models\User;
use App\Models\WoodTexture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Files are not written: tests that read the source image store it themselves.
 *
 * @extends Factory<FragmentProject>
 */
class FragmentProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->words(2, true)),
            'created_by' => User::factory(),
            'source_path' => 'fragment-tool/projects/test/source.png',
            'thumbnail_path' => 'fragment-tool/projects/test/thumbnail.jpg',
            'source_width' => 160,
            'source_height' => 160,
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_width' => 160,
            'crop_height' => 160,
            'format_id' => Format::factory()->forTool()->state(['cols' => 16, 'rows' => 16]),
            'palette_id' => Palette::factory(),
            'background_id' => WoodTexture::factory(),
            'hint' => null,
            'cost_usd' => 0,
        ];
    }
}
