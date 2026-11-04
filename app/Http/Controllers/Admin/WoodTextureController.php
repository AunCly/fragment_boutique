<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Palette;
use App\Models\WoodTexture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WoodTextureController extends Controller
{
    public function index(): View
    {
        $textures = WoodTexture::ordered()->get();
        $usedIds = WoodTexture::idsUsedByProjects();

        return view('admin.builder.wood-textures.index', compact('textures', 'usedIds'));
    }

    public function create(): View
    {
        return view('admin.builder.wood-textures.form', [
            'texture' => new WoodTexture,
            'palettes' => Palette::ordered()->get(),
        ]);
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
            'palette_ids' => ['nullable', 'array'],
            'palette_ids.*' => ['integer', 'distinct', 'exists:palettes,id'],
        ]);

        $path = $request->file('texture')->store('wood_textures', 'public');

        $woodTexture = WoodTexture::create([
            'slug' => $this->uniqueSlug($request->input('name')),
            'name' => $validated['name'],
            'hex' => $validated['hex'],
            'texture_path' => $path,
            'grid_code' => $validated['grid_code'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'tags' => $request->input('tags', []),
        ]);

        $woodTexture->palettes()->sync($validated['palette_ids'] ?? []);

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence ajoutée.');
    }

    public function edit(WoodTexture $woodTexture): View
    {
        return view('admin.builder.wood-textures.form', [
            'texture' => $woodTexture->load('palettes'),
            'palettes' => Palette::ordered()->get(),
        ]);
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
            'palette_ids' => ['nullable', 'array'],
            'palette_ids.*' => ['integer', 'distinct', 'exists:palettes,id'],
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

        $removedPaletteIds = array_diff($woodTexture->palettes()->pluck('palettes.id')->all(), $validated['palette_ids'] ?? []);
        if (Palette::whereIn('id', $removedPaletteIds)->whereHas('projects')->exists()) {
            throw ValidationException::withMessages([
                'palette_ids' => 'Cette essence ne peut pas être retirée d\'une palette utilisée par des projets de l\'outil. Dupliquez la palette pour en créer une variante.',
            ]);
        }

        $woodTexture->update($data);
        $woodTexture->palettes()->sync($validated['palette_ids'] ?? []);

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence mise à jour.');
    }

    public function destroy(WoodTexture $woodTexture): RedirectResponse
    {
        if ($woodTexture->isUsedByProjects()) {
            return redirect()->route('admin.wood-textures.index')->with('error', 'Cette essence est utilisée par des projets de l\'outil : archivez-la plutôt.');
        }

        if ($woodTexture->texture_path) {
            Storage::disk('public')->delete($woodTexture->texture_path);
        }

        $woodTexture->delete();

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence supprimée.');
    }

    /**
     * Marked inactive; the projects that use it keep working.
     */
    public function archive(WoodTexture $woodTexture): RedirectResponse
    {
        $woodTexture->update(['is_active' => false]);

        return redirect()->route('admin.wood-textures.index')->with('success', 'Essence archivée.');
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
