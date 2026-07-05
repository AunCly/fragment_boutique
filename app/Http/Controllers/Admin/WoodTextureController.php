<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WoodTexture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WoodTextureController extends Controller
{
    public function index(): View
    {
        $textures = WoodTexture::ordered()->get();

        return view('admin.builder.wood-textures.index', compact('textures'));
    }

    public function create(): View
    {
        return view('admin.builder.wood-textures.form', ['texture' => new WoodTexture]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'texture' => ['required', 'image', 'max:5120'],
            'grid_code' => ['nullable', 'string', 'max:4'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $path = $request->file('texture')->store('wood_textures', 'public');

        WoodTexture::create([
            'slug' => $this->uniqueSlug($request->input('name')),
            'name' => $validated['name'],
            'hex' => $validated['hex'],
            'texture_path' => $path,
            'grid_code' => $validated['grid_code'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'tags' => $request->input('tags', []),
        ]);

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence ajoutée.');
    }

    public function edit(WoodTexture $woodTexture): View
    {
        return view('admin.builder.wood-textures.form', ['texture' => $woodTexture]);
    }

    public function update(Request $request, WoodTexture $woodTexture): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'texture' => ['nullable', 'image', 'max:5120'],
            'grid_code' => ['nullable', 'string', 'max:4'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data = [
            'name' => $validated['name'],
            'hex' => $validated['hex'],
            'grid_code' => $validated['grid_code'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'tags' => $request->input('tags', []),
        ];

        if ($request->hasFile('texture')) {
            if ($woodTexture->texture_path) {
                Storage::disk('public')->delete($woodTexture->texture_path);
            }
            $data['texture_path'] = $request->file('texture')->store('wood_textures', 'public');
        }

        $woodTexture->update($data);

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence mise à jour.');
    }

    public function destroy(WoodTexture $woodTexture): RedirectResponse
    {
        if ($woodTexture->texture_path) {
            Storage::disk('public')->delete($woodTexture->texture_path);
        }

        $woodTexture->delete();

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence supprimée.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (WoodTexture::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
