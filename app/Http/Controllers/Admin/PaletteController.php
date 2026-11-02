<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Palette;
use App\Models\WoodTexture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaletteController extends Controller
{
    public function index(): View
    {
        $palettes = Palette::with('woodTextures')->ordered()->get();

        return view('admin.tool.palettes.index', compact('palettes'));
    }

    public function create(): View
    {
        return view('admin.tool.palettes.form', [
            'palette' => new Palette,
            'woodTextures' => WoodTexture::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePalette($request);

        $palette = Palette::create([
            ...collect($validated)->except('wood_texture_ids')->all(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $palette->woodTextures()->sync($validated['wood_texture_ids']);

        return redirect()->route('admin.palettes.index')->with('success', 'Palette ajoutée.');
    }

    public function edit(Palette $palette): View
    {
        return view('admin.tool.palettes.form', [
            'palette' => $palette->load('woodTextures'),
            'woodTextures' => WoodTexture::ordered()->get(),
        ]);
    }

    public function update(Request $request, Palette $palette): RedirectResponse
    {
        $validated = $this->validatePalette($request);

        $palette->update([
            ...collect($validated)->except('wood_texture_ids')->all(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $palette->woodTextures()->sync($validated['wood_texture_ids']);

        return redirect()->route('admin.palettes.index')->with('success', 'Palette mise à jour.');
    }

    public function destroy(Palette $palette): RedirectResponse
    {
        $palette->delete();

        return redirect()->route('admin.palettes.index')->with('success', 'Palette supprimée.');
    }

    /**
     * @return array{name: string, description: ?string, sort_order: int, is_active?: bool, wood_texture_ids: array<int, int>}
     */
    private function validatePalette(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'wood_texture_ids' => ['required', 'array', 'min:'.Palette::MIN_ESSENCES],
            'wood_texture_ids.*' => ['integer', 'distinct', 'exists:wood_textures,id'],
        ], [
            'wood_texture_ids.required' => 'Sélectionnez au moins '.Palette::MIN_ESSENCES.' essences.',
            'wood_texture_ids.min' => 'Sélectionnez au moins '.Palette::MIN_ESSENCES.' essences.',
        ]);
    }
}
