<?php

namespace Database\Seeders;

use App\Models\Palette;
use App\Models\WoodTexture;
use Illuminate\Database\Seeder;

class PaletteSeeder extends Seeder
{
    /**
     * Seed the original FragmentTool palette (spec §3). Requires WoodTextureSeeder.
     */
    public function run(): void
    {
        $palette = Palette::updateOrCreate(
            ['name' => 'Bois naturels'],
            ['description' => 'Palette d\'origine de l\'outil Fragment', 'sort_order' => 1, 'is_active' => true],
        );

        $woodTextureIds = WoodTexture::whereIn('slug', [
            'sycomore',
            'hetre',
            'orme',
            'merisier',
            'poirier_alisier',
            'noyer',
            'chene_fume',
            'tulipier_noir',
        ])->pluck('id');

        $palette->woodTextures()->sync($woodTextureIds);
    }
}
