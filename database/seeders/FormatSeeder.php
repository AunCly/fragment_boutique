<?php

namespace Database\Seeders;

use App\Models\Format;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            ['slug' => 'square-s',    'label' => '16 × 16 cm', 'cols' => 16, 'rows' => 16, 'sort_order' => 1],
            ['slug' => 'square-l',    'label' => '32 × 32 cm', 'cols' => 32, 'rows' => 32, 'sort_order' => 2],
            ['slug' => 'landscape-s', 'label' => '32 × 16 cm', 'cols' => 32, 'rows' => 16, 'sort_order' => 3],
            ['slug' => 'landscape-l', 'label' => '64 × 32 cm', 'cols' => 64, 'rows' => 32, 'sort_order' => 4],
            ['slug' => 'portrait-s',  'label' => '16 × 32 cm', 'cols' => 16, 'rows' => 32, 'sort_order' => 5],
            ['slug' => 'portrait-l',  'label' => '32 × 64 cm', 'cols' => 32, 'rows' => 64, 'sort_order' => 6],
        ];

        foreach ($formats as $data) {
            Format::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
