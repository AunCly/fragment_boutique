<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FragmentTool\ToolImageRequest;
use App\Http\Requests\Admin\FragmentTool\ToolRequest;
use App\Models\Format;
use App\Models\Palette;
use App\Services\FragmentTool\Ai\AiPipeline;
use App\Services\FragmentTool\Ai\FakeGeminiClient;
use App\Services\FragmentTool\Ai\GeminiException;
use App\Services\FragmentTool\ConversionPipeline;
use App\Services\FragmentTool\Grid;
use App\Services\FragmentTool\Matching\MatcherRegistry;
use App\Services\FragmentTool\MotifPainter;
use App\Services\FragmentTool\Reduction\ReducerRegistry;
use App\Services\FragmentTool\Rendering\FlatRenderer;
use App\Services\FragmentTool\Rendering\PlanRenderer;
use App\Services\FragmentTool\Rendering\WoodRenderer;
use App\Services\FragmentTool\ToolPalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class FragmentToolController extends Controller
{
    /** @var array<string, string> */
    private const SHAPES = ['square' => 'Carré', 'landscape' => 'Paysage', 'portrait' => 'Portrait'];

    public function __construct(
        private readonly ConversionPipeline $conversion,
        private readonly AiPipeline $ai,
        private readonly ReducerRegistry $reducers,
        private readonly MatcherRegistry $matchers,
    ) {}

    public function index(WoodRenderer $wood): View
    {
        $formats = Format::forTool()->ordered()->get();

        $shapes = collect(self::SHAPES)
            ->map(fn (string $label, string $shape) => [
                'key' => $shape,
                'label' => $label,
                'formats' => $formats->where('shape', $shape)
                    ->sortBy(fn (Format $format) => $format->cols * $format->rows)
                    ->map(fn (Format $format) => [
                        'id' => $format->id,
                        'label' => "{$format->cols} × {$format->rows}",
                        'cols' => $format->cols,
                        'rows' => $format->rows,
                    ])->values(),
            ])
            ->filter(fn (array $shape) => $shape['formats']->isNotEmpty())
            ->values();

        $palettes = Palette::active()->ordered()->with('woodTextures')->get()
            ->filter(fn (Palette $palette) => $palette->woodTextures->count() >= Palette::MIN_ESSENCES)
            ->map(function (Palette $palette) use ($wood) {
                $toolPalette = ToolPalette::fromPalette($palette);

                return [
                    'id' => $palette->id,
                    'name' => $palette->name,
                    'essences' => $toolPalette->toArray(),
                    'defaultBackgroundId' => $toolPalette->defaultBackgroundId(),
                    'haloId' => $toolPalette->haloId(),
                    'defaultMotifId' => $toolPalette->defaultMotifId(),
                    'texturedIds' => $wood->essencesWithTexture($toolPalette),
                ];
            })
            ->values();

        $config = [
            'shapes' => $shapes,
            'palettes' => $palettes,
            'motifs' => MotifPainter::MOTIFS,
            'densities' => MotifPainter::DENSITIES,
            'maxUploadKb' => config('fragment_tool.max_upload_kb'),
            'ai' => [
                'enabled' => $this->ai->isEnabled(),
                'counts' => config('fragment_tool.ai.counts'),
                'hintMaxLength' => config('fragment_tool.ai.hint_max_length'),
                'imageModel' => config('fragment_tool.ai.image_model'),
                'judgeModel' => config('fragment_tool.ai.judge_model'),
                'suggestModel' => config('fragment_tool.ai.suggest_model'),
            ],
            'routes' => [
                'generate' => route('admin.tool.ai.generate'),
                'refine' => route('admin.tool.ai.refine'),
                'suggest' => route('admin.tool.ai.suggest'),
                'motifs' => route('admin.tool.motifs'),
                'render' => route('admin.tool.render'),
                'archive' => route('admin.tool.archive'),
            ],
        ];

        return view('admin.tool.index', compact('config'));
    }

    /**
     * Classic conversion (no AI) with one reduction × matching pair.
     */
    public function convert(ToolImageRequest $request): JsonResponse
    {
        $request->validate([
            'reduction' => ['required', 'string', Rule::in(array_keys($this->reducers->labels()))],
            'matching' => ['required', 'string', Rule::in(array_keys($this->matchers->labels()))],
            'brightness' => ['nullable', 'integer', 'between:-100,100'],
            'contrast' => ['nullable', 'integer', 'between:-100,100'],
        ]);
        $format = $request->toolFormat();

        $grid = $this->conversion->convert(
            $request->source(),
            $request->crop(),
            $format->cols,
            $format->rows,
            $request->palette(),
            $request->backgroundId(),
            $request->string('reduction')->toString(),
            $request->string('matching')->toString(),
            $request->integer('brightness'),
            $request->integer('contrast'),
        );

        return response()->json(['grid' => $grid->toArray(), 'counts' => $grid->counts()]);
    }

    /**
     * Classic conversion, every reduction × matching combination (API only).
     */
    public function preview(ToolImageRequest $request): JsonResponse
    {
        $request->validate([
            'brightness' => ['nullable', 'integer', 'between:-100,100'],
            'contrast' => ['nullable', 'integer', 'between:-100,100'],
        ]);
        $format = $request->toolFormat();

        $grids = $this->conversion->preview(
            $request->source(),
            $request->crop(),
            $format->cols,
            $format->rows,
            $request->palette(),
            $request->backgroundId(),
            $request->integer('brightness'),
            $request->integer('contrast'),
        );

        return response()->json([
            'grids' => array_map(fn ($grid) => ['grid' => $grid->toArray(), 'counts' => $grid->counts()], $grids),
        ]);
    }

    public function generate(ToolImageRequest $request): JsonResponse|StreamedResponse
    {
        $request->validate([
            'count' => ['required', 'integer', Rule::in(config('fragment_tool.ai.counts'))],
            'hint' => ['nullable', 'string', 'max:'.config('fragment_tool.ai.hint_max_length')],
        ]);

        $ai = $this->pipeline($request);
        if (! $ai->isEnabled()) {
            return $this->aiDisabled();
        }

        $format = $request->toolFormat();
        $source = $request->source();
        $crop = $request->crop();
        $palette = $request->palette();
        $backgroundId = $request->backgroundId();

        return $this->ndjson(fn (callable $emit) => $ai->generate(
            $source, $crop, $format->cols, $format->rows, $palette, $backgroundId,
            $request->integer('count'), $request->input('hint'), $emit,
        ));
    }

    public function refine(ToolImageRequest $request): JsonResponse|StreamedResponse
    {
        $request->validate([
            'grid' => ['required', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'hint' => ['nullable', 'string', 'max:'.config('fragment_tool.ai.hint_max_length')],
        ]);

        $ai = $this->pipeline($request);
        if (! $ai->isEnabled()) {
            return $this->aiDisabled();
        }

        $format = $request->toolFormat();
        $source = $request->source();
        $crop = $request->crop();
        $palette = $request->palette();
        $backgroundId = $request->backgroundId();
        $grid = $request->grid();
        $remarks = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $request->input('remarks')) ?: [])));

        return $this->ndjson(fn (callable $emit) => $ai->refine(
            $source, $crop, $format->cols, $format->rows, $palette, $backgroundId,
            $grid, $remarks ?: ['Améliore la lisibilité du sujet.'], $request->input('hint'), $emit,
        ));
    }

    public function suggest(ToolImageRequest $request): JsonResponse
    {
        $request->validate(['grid' => ['required', 'string']]);

        $ai = $this->pipeline($request);
        if (! $ai->isEnabled()) {
            return $this->aiDisabled();
        }

        try {
            return response()->json($ai->suggest(
                $request->source(),
                $request->crop(),
                $request->palette(),
                $request->backgroundId(),
                $request->grid(),
            ));
        } catch (GeminiException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function motifs(ToolRequest $request, MotifPainter $painter): JsonResponse
    {
        $request->validate([
            'grid' => ['required', 'string'],
            'motif' => ['required', 'string', Rule::in(array_keys(MotifPainter::MOTIFS))],
            'density' => ['required', 'string', Rule::in(array_keys(MotifPainter::DENSITIES))],
            'essence_id' => ['required', 'integer'],
        ]);

        $palette = $request->palette();
        $essenceId = $request->integer('essence_id');
        if (! $palette->has($essenceId)) {
            return response()->json(['message' => 'L\'essence du motif doit appartenir à la palette.', 'errors' => ['essence_id' => ['L\'essence du motif doit appartenir à la palette.']]], 422);
        }

        try {
            $grid = $painter->apply($request->grid(), $request->string('motif')->toString(), $request->string('density')->toString(), $essenceId, $request->backgroundId());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['grid' => $grid->toArray()]);
    }

    public function render(ToolRequest $request, FlatRenderer $flat, WoodRenderer $wood, PlanRenderer $plan): Response
    {
        $request->validate([
            'grid' => ['required', 'string'],
            'kind' => ['required', 'string', Rule::in(['image', 'wood', 'plan'])],
            'name' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:80'],
        ]);

        $grid = $request->grid();
        $kind = $request->input('kind');
        $png = $this->renderKind($kind, $request, $grid, $flat, $wood, $plan);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$this->renderFilename($kind, $request, $grid).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The three renders (flat image, wood, plan) in one ZIP, inside a single folder.
     */
    public function archive(ToolRequest $request, FlatRenderer $flat, WoodRenderer $wood, PlanRenderer $plan): Response
    {
        $request->validate([
            'grid' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:80'],
        ]);

        $grid = $request->grid();
        $folder = $this->renderBase($request, $grid);
        $path = tempnam(sys_get_temp_dir(), 'fragment_zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach (['image', 'wood', 'plan'] as $kind) {
            $zip->addFromString(
                "{$folder}/".$this->renderFilename($kind, $request, $grid),
                $this->renderKind($kind, $request, $grid, $flat, $wood, $plan),
            );
        }

        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return response($bytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$folder.'.zip"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function renderKind(string $kind, ToolRequest $request, Grid $grid, FlatRenderer $flat, WoodRenderer $wood, PlanRenderer $plan): string
    {
        $palette = $request->palette();

        return match ($kind) {
            'image' => $flat->render($grid, $palette),
            'wood' => $wood->render($grid, $palette),
            'plan' => $plan->render($grid, $palette, $request->input('name') ?: 'Fragment', $request->input('model')),
        };
    }

    private function renderFilename(string $kind, ToolRequest $request, Grid $grid): string
    {
        $base = $this->renderBase($request, $grid);

        return match ($kind) {
            'image' => "{$base}.png",
            'wood' => "{$base}_bois.png",
            'plan' => "{$base}_plan.png",
        };
    }

    private function renderBase(ToolRequest $request, Grid $grid): string
    {
        $name = Str::slug($request->input('name') ?: 'fragment', '_') ?: 'fragment';

        return "{$name}_{$grid->cols}x{$grid->rows}";
    }

    /**
     * Test mode swaps Gemini for a local fake: the whole flow runs without any call or cost.
     */
    private function pipeline(ToolRequest $request): AiPipeline
    {
        return $request->boolean('test_mode')
            ? app(AiPipeline::class, ['client' => app(FakeGeminiClient::class)])
            : $this->ai;
    }

    /**
     * NDJSON stream: one JSON event per line, flushed as soon as it is emitted.
     *
     * @param  callable(callable(array<string, mixed>): void): void  $run
     */
    private function ndjson(callable $run): StreamedResponse
    {
        return response()->stream(function () use ($run) {
            set_time_limit(0);

            $run(function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE)."\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function aiDisabled(): JsonResponse
    {
        return response()->json(['message' => 'Redessin IA désactivé : aucune clé GEMINI_API_KEY configurée.'], 503);
    }
}
