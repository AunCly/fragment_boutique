<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormatController extends Controller
{
    public function index(): View
    {
        $formats = Format::withExists('projects')->ordered()->get();

        return view('admin.builder.formats.index', compact('formats'));
    }

    public function create(): View
    {
        return view('admin.builder.formats.form', ['format' => new Format]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:60', 'unique:formats,slug'],
            'label' => ['required', 'string', 'max:50'],
            'cols' => ['required', 'integer', 'min:1', 'max:500'],
            'rows' => ['required', 'integer', 'min:1', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_tool_enabled' => ['boolean'],
        ]);

        Format::create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'is_tool_enabled' => $request->boolean('is_tool_enabled'),
        ]);

        return redirect()->route('admin.formats.index')->with('success', 'Format ajouté.');
    }

    public function edit(Format $format): View
    {
        return view('admin.builder.formats.form', compact('format'));
    }

    public function update(Request $request, Format $format): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:60', 'unique:formats,slug,'.$format->id],
            'label' => ['required', 'string', 'max:50'],
            'cols' => ['required', 'integer', 'min:1', 'max:500'],
            'rows' => ['required', 'integer', 'min:1', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_tool_enabled' => ['boolean'],
        ]);

        $format->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'is_tool_enabled' => $request->boolean('is_tool_enabled'),
        ]);

        return redirect()->route('admin.formats.index')->with('success', 'Format mis à jour.');
    }

    public function destroy(Format $format): RedirectResponse
    {
        if ($format->isUsedByProjects()) {
            return redirect()->route('admin.formats.index')->with('error', 'Ce format est utilisé par des projets de l\'outil : archivez-le plutôt.');
        }

        $format->delete();

        return redirect()->route('admin.formats.index')->with('success', 'Format supprimé.');
    }

    /**
     * Hidden from the shop and from new tool projects; the projects that use it keep working.
     */
    public function archive(Format $format): RedirectResponse
    {
        $format->update(['is_active' => false, 'is_tool_enabled' => false]);

        return redirect()->route('admin.formats.index')->with('success', 'Format archivé.');
    }
}
