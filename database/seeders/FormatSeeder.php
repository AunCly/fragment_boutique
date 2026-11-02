<?php

namespace Database\Seeders;

use App\Models\Format;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            // Shop builder formats, also available in the Fragment tool.
            ['slug' => 'square-s',     'label' => '16 × 16 cm', 'cols' => 16, 'rows' => 16, 'sort_order' => 1,  'is_tool_enabled' => true],
            ['slug' => 'square-l',     'label' => '32 × 32 cm', 'cols' => 32, 'rows' => 32, 'sort_order' => 2,  'is_tool_enabled' => true],
            ['slug' => 'landscape-s',  'label' => '32 × 16 cm', 'cols' => 32, 'rows' => 16, 'sort_order' => 3,  'is_tool_enabled' => true],
            ['slug' => 'landscape-l',  'label' => '64 × 32 cm', 'cols' => 64, 'rows' => 32, 'sort_order' => 4,  'is_tool_enabled' => true],
            ['slug' => 'portrait-s',   'label' => '16 × 32 cm', 'cols' => 16, 'rows' => 32, 'sort_order' => 5,  'is_tool_enabled' => true],
            ['slug' => 'portrait-l',   'label' => '32 × 64 cm', 'cols' => 32, 'rows' => 64, 'sort_order' => 6,  'is_tool_enabled' => true],
            // Fragment tool only formats, hidden from the shop builder.
            ['slug' => 'square-xl',    'label' => '64 × 64 cm', 'cols' => 64, 'rows' => 64, 'sort_order' => 7,  'is_active' => false, 'is_tool_enabled' => true],
            ['slug' => 'landscape-m',  'label' => '48 × 24 cm', 'cols' => 48, 'rows' => 24, 'sort_order' => 8,  'is_active' => false, 'is_tool_enabled' => true],
            ['slug' => 'landscape-xl', 'label' => '96 × 48 cm', 'cols' => 96, 'rows' => 48, 'sort_order' => 9,  'is_active' => false, 'is_tool_enabled' => true],
            ['slug' => 'portrait-m',   'label' => '24 × 48 cm', 'cols' => 24, 'rows' => 48, 'sort_order' => 10, 'is_active' => false, 'is_tool_enabled' => true],
            ['slug' => 'portrait-xl',  'label' => '48 × 96 cm', 'cols' => 48, 'rows' => 96, 'sort_order' => 11, 'is_active' => false, 'is_tool_enabled' => true],
        ];

        foreach ($formats as $data) {
            Format::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
