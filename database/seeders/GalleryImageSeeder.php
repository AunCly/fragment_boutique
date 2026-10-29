<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class GalleryImageSeeder extends Seeder
{
    /**
     * Copy the original static gallery images to the public disk and register them.
     */
    public function run(): void
    {
        $files = [
            'gallery-1-Cuy0LPkR.jpg',
            'product-owl-D5LQ9sc5.jpg',
            'gallery-2-Dc8Qmn3J.jpg',
            'product-mountains-C2h_MNwy.jpg',
            'gallery-3-CCzQGLN0.jpg',
            'product-geo2-BEs2mhOM.jpg',
        ];

        foreach ($files as $index => $file) {
            $source = resource_path('images/gallery/'.$file);
            $path = 'gallery/'.$file;

            if (! File::exists($source)) {
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, File::get($source));
            }

            GalleryImage::updateOrCreate(['path' => $path], [
                'alt' => GalleryImage::DEFAULT_ALT,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
