<?php

namespace Tests\Feature\FragmentTool;

use App\Models\Format;
use App\Models\Palette;
use App\Models\User;
use App\Services\FragmentTool\Ai\AiProjector;
use App\Services\FragmentTool\Ai\AiPrompts;
use App\Services\FragmentTool\Ai\GeminiClient;
use App\Services\FragmentTool\Ai\GeminiException;
use App\Services\FragmentTool\Ai\GeminiUsage;
use App\Services\FragmentTool\Ai\JsonAnswer;
use App\Services\FragmentTool\SourceImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\Feature\FragmentTool\Concerns\FakesGemini;
use Tests\TestCase;

/**
 * Gemini is always faked: these tests never reach Google.
 */
class AiToolTest extends TestCase
{
    use BuildsToolFixtures;
    use FakesGemini;
    use RefreshDatabase;

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    private User $admin;

    private Palette $palette;

    private Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        config(['services.gemini.key' => 'test-key']);
        Http::preventStrayRequests();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->palette = $this->createToolPalette();
        $this->format = $this->createToolFormat(16, 16);
    }

    private function id(string $slug): int
    {
        return $this->essenceId($this->palette, $slug);
    }

    /**
     * Grid expected after projection of modelImage(): merisier subject, sycomore halo, hêtre background.
     *
     * @return list<list<int>>
     */
    private function expectedProjection(): array
    {
        $cells = [];
        for ($r = 0; $r < 16; $r++) {
            for ($c = 0; $c < 16; $c++) {
                $inside = $r >= 5 && $c >= 5 && $r < 11 && $c < 11;
                $halo = ! $inside && $r >= 4 && $c >= 4 && $r < 12 && $c < 12;
                $cells[$r][$c] = $inside ? $this->id('merisier') : ($halo ? $this->id('sycomore') : $this->id('hetre'));
            }
        }

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function imagePayload(array $extra = []): array
    {
        return [
            'format_id' => $this->format->id,
            'palette_id' => $this->palette->id,
            'image' => $this->uploadedPng($this->pixelArtPng($this->subjectHexGrid(16, 16, '#d6b47c', '#c26d2d'), 10)),
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_width' => 160,
            'crop_height' => 160,
            ...$extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postTool(string $route, array $data): TestResponse
    {
        return $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route($route), $data);
    }

    /* ---------------------------------------------------------------- Generation */

    public function test_generation_streams_projected_candidates_then_the_judge_review(): void
    {
        Http::fake(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction())
            : Http::response($this->textInteraction([
                'candidates' => [
                    ['index' => 0, 'score' => 7.5, 'problems' => ['Le contour est cassé en haut.']],
                    ['index' => 1, 'score' => 14, 'problems' => []],
                ],
                'preferred' => 1,
            ])));

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 2, 'hint' => 'garder le chapeau'])));

        $this->assertSame('project', $events[0]['type'], 'A first real generation creates the project.');
        $this->assertSame('stage', $events[1]['type']);
        $candidates = $this->ofType($events, 'candidate');
        $this->assertCount(2, $candidates);
        $this->assertSame($this->expectedProjection(), $candidates[0]['grid']);

        $result = $this->ofType($events, 'result')[0];
        $this->assertSame(1, $result['preferred']);
        $this->assertSame(7.5, $result['candidates'][0]['score']);
        $this->assertSame(10, $result['candidates'][1]['score'], 'Scores are clamped to 0–10.');
        $this->assertSame(['Le contour est cassé en haut.'], $result['candidates'][0]['problems']);
        $this->assertSame(['base', 'contrast'], array_column($result['candidates'], 'style'));
        $this->assertCount(3, $result['usage']['calls']);
        $this->assertGreaterThan(0, $result['usage']['cost_usd']);

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === self::ENDPOINT
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $data['store'] === false
                && $data['model'] === 'gemini-nano-banana-2.1'
                && $data['response_format']['aspect_ratio'] === '1:1'
                && $data['input'][1]['type'] === 'image'
                && str_contains($data['input'][0]['text'], '8 × 8')
                && str_contains($data['input'][0]['text'], 'garder le chapeau');
        });
        Http::assertSent(fn (Request $request) => ($request->data()['model'] ?? null) === 'gemini-3.1-flash-lite'
            && count($request->data()['input']) === 4);
    }

    public function test_generation_uses_the_chosen_image_model(): void
    {
        $this->fakeGeminiWithJudge();

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1, 'image_model' => 'gemini-3-pro-image'])));

        $this->assertSame('gemini-3-pro-image', $this->ofType($events, 'result')[0]['usage']['calls'][0]['model']);
        Http::assertSent(fn (Request $request) => $this->isImageRequest($request) && $request->data()['model'] === 'gemini-3-pro-image');
        Http::assertNotSent(fn (Request $request) => ($request->data()['model'] ?? null) === 'gemini-nano-banana-2.1');
    }

    public function test_generation_rejects_an_unknown_image_model(): void
    {
        Http::fake();

        $this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1, 'image_model' => 'gemini-unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image_model');
        Http::assertNothingSent();
    }

    public function test_outline_instruction_is_sent_by_default(): void
    {
        $this->fakeGeminiWithJudge();

        $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1])));

        Http::assertSent(fn (Request $request) => $this->isImageRequest($request)
            && str_contains($request->data()['input'][0]['text'], 'Outline the subject'));
        Http::assertSent(fn (Request $request) => ! $this->isImageRequest($request)
            && str_contains($request->data()['input'][0]['text'], 'light outline'));
    }

    public function test_outline_instruction_can_be_turned_off(): void
    {
        $this->fakeGeminiWithJudge();

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1, 'outline' => '0'])));

        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => str_contains($request->data()['input'][0]['text'], 'outline'));
        $result = $this->ofType($events, 'result')[0];
        $this->assertNotContains($this->id('sycomore'), array_merge(...$result['candidates'][0]['grid']), 'No halo is imposed by the projection.');
    }

    public function test_refinement_uses_the_chosen_model_and_outline_option(): void
    {
        $this->fakeGeminiWithJudge();
        $before = array_fill(0, 16, array_fill(0, 16, $this->id('hetre')));

        $events = $this->events($this->postTool('admin.tool.ai.refine', $this->imagePayload([
            'grid' => json_encode($before),
            'image_model' => 'gemini-3.1-flash-lite-image',
            'outline' => '0',
        ])));

        $this->assertNotContains($this->id('sycomore'), array_merge(...$this->ofType($events, 'result')[0]['grid']));

        Http::assertSent(fn (Request $request) => $this->isImageRequest($request)
            && $request->data()['model'] === 'gemini-3.1-flash-lite-image'
            && ! str_contains($request->data()['input'][0]['text'], 'outline'));
    }

    public function test_test_mode_reports_the_chosen_image_model(): void
    {
        config(['services.gemini.key' => null, 'fragment_tool.ai.test_mode_delay_ms' => 0]);
        Http::fake();

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1, 'test_mode' => '1', 'image_model' => 'gemini-3.1-flash-lite-image'])));

        $this->assertSame('gemini-3.1-flash-lite-image', $this->ofType($events, 'result')[0]['usage']['calls'][0]['model']);
        Http::assertNothingSent();
    }

    public function test_tool_page_offers_the_image_models_with_the_default_preselected(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tool.index'))
            ->assertOk()
            ->assertSee('<option value="gemini-nano-banana-2.1" selected>', false)
            ->assertSee('gemini-3.1-flash-lite-image')
            ->assertSee('gemini-3-pro-image')
            ->assertSee('data-ref="outline"', false);
    }

    private function fakeGeminiWithJudge(): void
    {
        Http::fake(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction())
            : Http::response($this->textInteraction(['candidates' => [['index' => 0, 'score' => 8, 'problems' => []]], 'preferred' => 0])));
    }

    public function test_a_failed_image_does_not_stop_the_batch(): void
    {
        $imageCalls = 0;
        Http::fake(function (Request $request) use (&$imageCalls) {
            if (! $this->isImageRequest($request)) {
                return Http::response($this->textInteraction(['candidates' => [['index' => 0, 'score' => 6, 'problems' => []]], 'preferred' => 0]));
            }

            return ++$imageCalls === 1
                ? Http::response(['error' => ['message' => 'Quota exceeded']], 429)
                : Http::response($this->imageInteraction());
        });

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 2])));

        $this->assertCount(1, $this->ofType($events, 'candidate'));
        $this->assertStringContainsString('quota dépassé', $this->ofType($events, 'warning')[0]['message']);
        $result = $this->ofType($events, 'result')[0];
        $this->assertCount(1, $result['candidates']);
        $this->assertSame(1, $result['candidates'][0]['index']);
        $this->assertSame(1, $result['preferred'], 'The judge index is mapped back to the remaining candidate.');
    }

    public function test_an_overload_is_retried_once(): void
    {
        $imageCalls = 0;
        Http::fake(function (Request $request) use (&$imageCalls) {
            if (! $this->isImageRequest($request)) {
                return Http::response($this->textInteraction(['candidates' => [], 'preferred' => 0]));
            }

            return ++$imageCalls === 1 ? Http::response('busy', 503) : Http::response($this->imageInteraction());
        });

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1])));

        $this->assertSame(2, $imageCalls);
        $this->assertCount(1, $this->ofType($events, 'candidate'));
        $this->assertSame([], $this->ofType($events, 'warning'));
    }

    public function test_versions_are_kept_without_score_when_the_judge_fails(): void
    {
        Http::fake(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction())
            : Http::response(['error' => ['message' => 'boom']], 500));

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1])));

        $this->assertStringContainsString('Juge indisponible', $this->ofType($events, 'warning')[0]['message']);
        $result = $this->ofType($events, 'result')[0];
        $this->assertNull($result['candidates'][0]['score']);
        $this->assertNull($result['preferred']);
    }

    public function test_an_error_is_streamed_when_no_image_could_be_generated(): void
    {
        Http::fake(['*' => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Désolé']]]]])]);

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1])));

        $this->assertSame('error', end($events)['type']);
        $this->assertStringContainsString('aucune image', $this->ofType($events, 'warning')[0]['message']);
        Http::assertSentCount(1);
    }

    public function test_rectangular_formats_ask_for_the_closest_supported_ratio(): void
    {
        $this->format = $this->createToolFormat(32, 16);
        Http::fake(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction())
            : Http::response($this->textInteraction(['candidates' => [], 'preferred' => 0])));

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1, 'crop_y' => 40, 'crop_height' => 80])));

        $grid = $this->ofType($events, 'candidate')[0]['grid'];
        $this->assertCount(16, $grid);
        $this->assertCount(32, $grid[0]);
        Http::assertSent(fn (Request $request) => ($request->data()['response_format']['aspect_ratio'] ?? null) === '16:9'
            && str_contains($request->data()['input'][0]['text'], '16 × 8'));
    }

    /* ---------------------------------------------------------------- Refinement */

    public function test_refinement_adds_a_new_reviewed_version(): void
    {
        Http::fake(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction())
            : Http::response($this->textInteraction(['candidates' => [['index' => 0, 'score' => 8, 'problems' => ['RAS']]], 'preferred' => 0], fenced: false)));
        $before = array_fill(0, 16, array_fill(0, 16, $this->id('hetre')));

        $events = $this->events($this->postTool('admin.tool.ai.refine', $this->imagePayload([
            'grid' => json_encode($before),
            'remarks' => "L'œil gauche a disparu\n\nLe contour est cassé",
        ])));

        $result = $this->ofType($events, 'result')[0];
        $this->assertFalse($result['unchanged']);
        $this->assertSame($this->expectedProjection(), $result['grid']);
        $this->assertSame(8, $result['score']);
        Http::assertSent(fn (Request $request) => $this->isImageRequest($request)
            && count($request->data()['input']) === 3
            && str_contains($request->data()['input'][0]['text'], "- L'œil gauche a disparu\n- Le contour est cassé"));
    }

    public function test_refinement_reports_an_unchanged_grid_without_judging(): void
    {
        Http::fake(fn (Request $request) => Http::response($this->imageInteraction()));

        $events = $this->events($this->postTool('admin.tool.ai.refine', $this->imagePayload([
            'grid' => json_encode($this->expectedProjection()),
            'remarks' => 'Rien',
        ])));

        $this->assertTrue($this->ofType($events, 'result')[0]['unchanged']);
        Http::assertSentCount(1);
    }

    public function test_refinement_failure_is_streamed_as_an_error(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad']], 400)]);

        $events = $this->events($this->postTool('admin.tool.ai.refine', $this->imagePayload([
            'grid' => json_encode($this->expectedProjection()),
        ])));

        $this->assertSame('error', end($events)['type']);
        $this->assertStringContainsString('requête refusée', end($events)['message']);
    }

    public function test_refinement_validates_the_grid(): void
    {
        Http::fake();

        $this->postTool('admin.tool.ai.refine', $this->imagePayload(['grid' => '[[1]]']))->assertJsonValidationErrors('grid');

        Http::assertNothingSent();
    }

    /* ---------------------------------------------------------------- Suggestions */

    public function test_suggestions_are_validated_and_never_applied(): void
    {
        $grid = $this->expectedProjection();
        Http::fake(['*' => Http::response($this->textInteraction(['suggestions' => [
            ['row' => 6, 'col' => 7, 'code' => '8', 'reason' => 'Ajouter un œil'],
            ['row' => 6, 'col' => 7, 'code' => '7', 'reason' => 'Doublon de case'],
            ['row' => 1, 'col' => 1, 'code' => '2', 'reason' => 'Déjà en hêtre'],
            ['row' => 40, 'col' => 1, 'code' => '8', 'reason' => 'Hors grille'],
            ['row' => 2, 'col' => 2, 'code' => 'ZZ', 'reason' => 'Essence inconnue'],
        ]]))]);

        $response = $this->postTool('admin.tool.ai.suggest', $this->imagePayload(['grid' => json_encode($grid)]));

        $response->assertOk()->assertJsonCount(1, 'suggestions')->assertJson(['suggestions' => [[
            'row' => 5,
            'col' => 6,
            'essence_id' => $this->id('tulipier_noir'),
            'reason' => 'Ajouter un œil',
        ]]]);
        $this->assertSame(1, count($response->json('usage.calls')));
        Http::assertSent(fn (Request $request) => $request->data()['model'] === 'gemini-3.6-flash'
            && str_contains($request->data()['input'][0]['text'], '1 = Sycomore (#f2cc96)'));
    }

    public function test_suggestion_failure_returns_502(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'down']], 500)]);

        $this->postTool('admin.tool.ai.suggest', $this->imagePayload(['grid' => json_encode($this->expectedProjection())]))
            ->assertStatus(502)
            ->assertJsonStructure(['message']);
    }

    /* ---------------------------------------------------------------- Test mode */

    public function test_test_mode_runs_the_whole_generation_without_calling_gemini(): void
    {
        config(['services.gemini.key' => null, 'fragment_tool.ai.test_mode_delay_ms' => 0]);
        Http::fake();

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 4, 'test_mode' => '1'])));

        $this->assertCount(4, $this->ofType($events, 'candidate'));
        $result = $this->ofType($events, 'result')[0];
        $this->assertCount(4, $result['candidates']);
        $this->assertNotNull($result['preferred']);
        foreach ($result['candidates'] as $candidate) {
            $this->assertCount(16, $candidate['grid']);
            $this->assertGreaterThanOrEqual(5, $candidate['score']);
        }
        $this->assertSame(0.0, (float) $result['usage']['cost_usd']);
        $this->assertSame('gemini-nano-banana-2.1', $result['usage']['calls'][0]['model']);
        Http::assertNothingSent();
    }

    public function test_test_mode_refines_and_suggests_without_calling_gemini(): void
    {
        config(['services.gemini.key' => null, 'fragment_tool.ai.test_mode_delay_ms' => 0]);
        Http::fake();
        $grid = json_encode($this->expectedProjection());

        $events = $this->events($this->postTool('admin.tool.ai.refine', $this->imagePayload(['grid' => $grid, 'test_mode' => '1'])));
        $this->assertCount(1, $this->ofType($events, 'result'));
        $this->assertSame([], $this->ofType($events, 'error'));

        $response = $this->postTool('admin.tool.ai.suggest', $this->imagePayload(['grid' => $grid, 'test_mode' => '1']));
        $response->assertOk()->assertJsonPath('usage.cost_usd', 0);
        foreach ($response->json('suggestions') as $suggestion) {
            $this->assertSame('Suggestion de test.', $suggestion['reason']);
            $this->assertLessThan(16, $suggestion['row']);
        }

        Http::assertNothingSent();
    }

    public function test_without_test_mode_and_api_key_ai_stays_disabled(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1, 'test_mode' => '0']))->assertStatus(503);

        Http::assertNothingSent();
    }

    /* ---------------------------------------------------------------- Helpers */

    public function test_json_answers_are_read_tolerantly(): void
    {
        $this->assertSame(['a' => 1], JsonAnswer::parse("```json\n{\"a\": 1}\n```"));
        $this->assertSame(['a' => 1], JsonAnswer::parse('Voici : {"a": 1} merci'));
        $this->assertSame(10.0, JsonAnswer::score(42));
        $this->assertSame(0.0, JsonAnswer::score(-3));
        $this->assertNull(JsonAnswer::score('n/a'));

        $this->expectException(GeminiException::class);
        JsonAnswer::parse('pas de json');
    }

    public function test_cost_is_computed_from_tokens(): void
    {
        config(['fragment_tool.ai.prices_per_m' => ['test-model' => ['input' => 1.0, 'output' => 2.0, 'image' => 30.0]]]);

        $this->assertEqualsWithDelta(0.001 + 0.0006, (new GeminiUsage('test-model', 1000, 200, 100))->costUsd, 1e-9);
        $this->assertEqualsWithDelta(0.001 + 0.0387, (new GeminiUsage('test-model', 1000, 1290, 0, producesImage: true))->costUsd, 1e-9);
        $this->assertSame(0.0, (new GeminiUsage('unknown-model', 1000, 1000))->costUsd);
    }

    public function test_response_parsing_skips_thoughts(): void
    {
        $json = ['steps' => [
            ['type' => 'thought', 'content' => [['type' => 'text', 'text' => 'secret']]],
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'visible']]],
        ]];

        $this->assertSame('visible', GeminiClient::findText($json));
        $this->assertNull(GeminiClient::findImage($json));
        $this->assertSame('abc', GeminiClient::findImage(['output_image' => ['data' => base64_encode('abc')]]));
    }

    public function test_styles_are_reused_in_turn_for_large_batches(): void
    {
        $this->assertSame(['base'], AiPrompts::stylesFor(1));
        $this->assertSame(['base', 'contrast', 'faithful', 'cute'], AiPrompts::stylesFor(4));
        $this->assertSame(['base', 'contrast', 'faithful', 'cute', 'base', 'contrast'], AiPrompts::stylesFor(6));
        $this->assertCount(8, AiPrompts::stylesFor(8));
    }

    public function test_test_mode_generates_a_batch_of_eight(): void
    {
        config(['services.gemini.key' => null, 'fragment_tool.ai.test_mode_delay_ms' => 0]);
        Http::fake();

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 8, 'test_mode' => '1'])));

        $this->assertCount(8, $this->ofType($events, 'candidate'));
        $result = $this->ofType($events, 'result')[0];
        $this->assertSame(range(0, 7), array_column($result['candidates'], 'index'));
        Http::assertNothingSent();
    }

    public function test_prompts_ask_for_half_the_grid_and_never_list_the_essences(): void
    {
        $palette = $this->toolPalette($this->palette);
        $prompt = AiPrompts::generation(32, 32, $palette, $palette->defaultBackgroundId(), $palette->haloId(), 'cute', null);

        $this->assertStringContainsString('16 × 16', $prompt);
        $this->assertStringContainsString('#d6b47c', $prompt);
        $this->assertStringContainsString('#f2cc96', $prompt);
        $this->assertStringContainsString('kawaii', $prompt);
        $this->assertStringNotContainsString('Merisier', $prompt);
        $this->assertSame(['base'], AiPrompts::stylesFor(1));
        $this->assertSame(['base', 'contrast', 'faithful', 'cute'], AiPrompts::stylesFor(4));
        $this->assertSame('9:16', AiPrompts::aspectRatio(24, 48));
    }

    public function test_projection_imposes_background_and_halo(): void
    {
        $palette = $this->toolPalette($this->palette);

        $grid = (new AiProjector)->project($this->modelImage(), 16, 16, $palette, $this->id('hetre'), $this->id('sycomore'));

        $this->assertSame($this->expectedProjection(), $grid->toArray());
    }

    public function test_projection_without_halo_only_imposes_the_background(): void
    {
        $palette = $this->toolPalette($this->palette);

        $grid = (new AiProjector)->project($this->modelImage(), 16, 16, $palette, $this->id('hetre'), null);

        $this->assertSame([$this->id('hetre') => 256 - 36, $this->id('merisier') => 36], $grid->counts());
    }

    public function test_projection_of_a_finer_drawing_uses_the_contours_logic(): void
    {
        $palette = $this->toolPalette($this->palette);
        $finer = SourceImage::toJpeg(imagecreatefromstring($this->pixelArtPng($this->subjectHexGrid(64, 64, '#9fb4c8', '#c26d2d', margin: 20), 16)));

        $grid = (new AiProjector)->project($finer, 16, 16, $palette, $this->id('hetre'), $this->id('sycomore'));

        $this->assertSame($this->id('merisier'), $grid->get(8, 8));
        $this->assertSame($this->id('hetre'), $grid->get(0, 0));
    }
}
