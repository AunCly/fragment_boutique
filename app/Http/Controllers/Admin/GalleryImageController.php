<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GalleryImageController extends Controller
{
    public function index(): View
    {
        $images = GalleryImage::ordered()->get();

        return view('admin.gallery.index', compact('images'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['image', 'max:5120'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $nextSortOrder = (int) GalleryImage::max('sort_order') + 1;

        foreach ($request->file('images') as $file) {
            GalleryImage::create([
                'path' => $file->store('gallery', 'public'),
                'alt' => $request->input('alt'),
                'sort_order' => $nextSortOrder++,
            ]);
        }

        $count = count($request->file('images'));

        return redirect()->route('admin.gallery-images.index')
            ->with('success', $count > 1 ? "{$count} images ajoutées." : 'Image ajoutée.');
    }

    public function update(Request $request, GalleryImage $galleryImage): RedirectResponse
    {
        $validated = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $galleryImage->update($validated);

        return redirect()->route('admin.gallery-images.index')->with('success', 'Image mise à jour.');
    }

    public function destroy(GalleryImage $galleryImage): RedirectResponse
    {
        Storage::disk('public')->delete($galleryImage->path);

        $galleryImage->delete();

        return redirect()->route('admin.gallery-images.index')->with('success', 'Image supprimée.');
    }
}
