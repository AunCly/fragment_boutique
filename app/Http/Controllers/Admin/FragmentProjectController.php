<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FragmentTool\UpdateProjectRequest;
use App\Http\Requests\Admin\FragmentTool\UpdateVersionRequest;
use App\Models\FragmentProject;
use App\Models\FragmentVersion;
use App\Services\FragmentTool\MotifPainter;
use App\Services\FragmentTool\Rendering\FlatRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Saved projects of the Fragment tool, shared by every admin.
 */
class FragmentProjectController extends Controller
{
    /** Cell size of the final grid shown in the project list. */
    private const PREVIEW_CELL_PX = 6;

    public function index(): View
    {
        $projects = FragmentProject::with(['palette', 'format', 'creator'])
            ->withCount('versions')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(24);

        return view('admin.tool.projects.index', compact('projects'));
    }

    public function update(UpdateProjectRequest $request, FragmentProject $project): JsonResponse
    {
        $project->update($request->validated());

        return response()->json(['saved' => true]);
    }

    public function updateVersion(UpdateVersionRequest $request, FragmentProject $project, FragmentVersion $version): JsonResponse
    {
        $version->update($request->validated());
        $project->touch();

        return response()->json(['saved' => true]);
    }

    public function destroy(FragmentProject $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('admin.tool.projects.index')->with('success', 'Projet supprimé.');
    }

    public function source(FragmentProject $project): StreamedResponse
    {
        return $this->file($project->source_path);
    }

    public function thumbnail(FragmentProject $project): StreamedResponse
    {
        return $this->file($project->thumbnail_path);
    }

    /**
     * Final grid of the chosen version (motif and edits included), in flat colours.
     */
    public function preview(FragmentProject $project, FlatRenderer $flat, MotifPainter $painter): Response
    {
        $version = $project->selectedVersion;
        abort_if($version === null, 404);

        $palette = $project->toolPalette();
        $png = $flat->render($version->finalGrid($palette, $painter), $palette, self::PREVIEW_CELL_PX);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-cache']);
    }

    private function file(string $path): StreamedResponse
    {
        $disk = Storage::disk(FragmentProject::DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: ['Cache-Control' => 'private, max-age=86400']);
    }
}
