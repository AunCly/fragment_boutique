<?php

namespace Tests\Feature\FragmentTool;

use App\Models\Format;
use App\Models\FragmentAiRun;
use App\Models\FragmentProject;
use App\Models\FragmentVersion;
use App\Models\Palette;
use App\Models\User;
use App\Models\WoodTexture;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\Feature\FragmentTool\Concerns\FakesGemini;
use Tests\TestCase;

/**
 * Saved projects: every paid AI call is recorded, test mode records nothing.
 * Gemini is always faked.
 */
class ProjectTest extends TestCase
{
    use BuildsToolFixtures;
    use FakesGemini;
    use RefreshDatabase;

    private User $admin;

    private Palette $palette;

    private Format $format;

    /** @var Closure(Request): mixed Gemini answer of the current test */
    private Closure $gemini;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');
        config(['services.gemini.key' => 'test-key', 'fragment_tool.ai.test_mode_delay_ms' => 0]);
        $this->gemini = fn () => $this->fail('Unexpected Gemini call.');
        Http::fake(fn (Request $request) => ($this->gemini)($request));

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->palette = $this->createToolPalette();
        $this->format = $this->createToolFormat(16, 16);
    }

    private function id(string $slug): int
    {
        return $this->essenceId($this->palette, $slug);
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
            'image' => $this->uploadedPng($this->pixelArtPng($this->subjectHexGrid(16, 16, '#d6b47c', '#c26d2d'), 10), 'chat-roux.png'),
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

    /**
     * @param  Closure(Request): mixed  $answer
     */
    private function answerWith(Closure $answer): void
    {
        $this->gemini = $answer;
    }

    private function fakeGemini(float $score = 7.5): void
    {
        $this->answerWith(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction())
            : Http::response($this->textInteraction([
                'candidates' => [
                    ['index' => 0, 'score' => $score, 'problems' => ['Contour cassé.']],
                    ['index' => 1, 'score' => 9, 'problems' => []],
                ],
                'preferred' => 1,
            ])));
    }

    /**
     * Saved project with its source image stored, like after a first generation.
     */
    private function savedProject(): FragmentProject
    {
        $this->fakeGemini();
        $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 2])));

        return FragmentProject::sole();
    }

    /**
     * Second active palette sharing two essences of the first one.
     */
    private function otherPalette(): Palette
    {
        $palette = Palette::factory()->create();
        $palette->woodTextures()->attach([$this->id('hetre'), $this->id('noyer')]);

        return $palette;
    }

    /**
     * @return list<list<int>>
     */
    private function filledGrid(string $slug): array
    {
        return array_fill(0, 16, array_fill(0, 16, $this->id($slug)));
    }

    /* ---------------------------------------------------------------- Recording */

    public function test_a_first_generation_creates_the_project_and_saves_each_version(): void
    {
        $this->fakeGemini();

        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 2, 'hint' => 'garder le chapeau'])));

        $project = FragmentProject::sole();
        $this->assertSame('project', $events[0]['type']);
        $this->assertSame($project->id, $events[0]['project']['id']);
        $this->assertSame(route('admin.tool.projects.show', $project), $events[0]['project']['url']);

        $this->assertSame('chat-roux', $project->name);
        $this->assertSame($this->admin->id, $project->created_by);
        $this->assertSame([$this->format->id, $this->palette->id, $this->id('hetre')], [$project->format_id, $project->palette_id, $project->background_id]);
        $this->assertSame([0, 0, 160, 160], [$project->crop_x, $project->crop_y, $project->crop_width, $project->crop_height]);
        $this->assertSame('garder le chapeau', $project->hint);
        Storage::disk('local')->assertExists([$project->source_path, $project->thumbnail_path]);
        $this->assertSame(160, $project->source()->width());

        $versions = $project->versions;
        $this->assertCount(2, $versions);
        $this->assertSame([1, 2], $versions->pluck('number')->all());
        $this->assertSame(['base', 'contrast'], $versions->pluck('style')->all());
        $this->assertSame([7.5, 9.0], $versions->pluck('score')->all());
        $this->assertSame([false, true], $versions->pluck('is_preferred')->all());
        $this->assertSame(['Contour cassé.'], $versions[0]->problems);
        $this->assertSame(FragmentVersion::KIND_GENERATION, $versions[0]->kind);
        foreach ($versions as $version) {
            $this->assertCount(16, $version->grid);
            Storage::disk('local')->assertExists($version->raw_image_path);
        }

        $candidates = $this->ofType($events, 'candidate');
        $this->assertArrayNotHasKey('raw_image', $candidates[0]);
        $this->assertContains($candidates[0]['version']['id'], $versions->pluck('id')->all());
        $result = $this->ofType($events, 'result')[0];
        $this->assertSame($versions->pluck('id')->all(), array_column($result['versions'], 'id'));
        $this->assertTrue($result['versions'][1]['preferred']);

        $run = FragmentAiRun::sole();
        $this->assertSame(FragmentAiRun::KIND_GENERATE, $run->kind);
        $this->assertSame(FragmentAiRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(['count' => 2, 'hint' => 'garder le chapeau', 'image_model' => 'gemini-nano-banana-2.1', 'outline' => true], $run->parameters);
        $this->assertSame($this->admin->id, $run->user_id);
        $this->assertCount(3, $run->usage);
        $this->assertGreaterThan(0, $run->cost_usd);
        $this->assertEqualsWithDelta($run->cost_usd, $project->fresh()->cost_usd, 0.00001);
    }

    public function test_test_mode_records_nothing(): void
    {
        $events = $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 2, 'test_mode' => '1'])));
        $grid = json_encode($this->ofType($events, 'candidate')[0]['grid']);
        $this->events($this->postTool('admin.tool.ai.refine', $this->imagePayload(['grid' => $grid, 'test_mode' => '1'])));
        $this->postTool('admin.tool.ai.suggest', $this->imagePayload(['grid' => $grid, 'test_mode' => '1']))->assertOk()->assertJsonPath('project', null);

        $this->assertSame([], $this->ofType($events, 'project'));
        $this->assertArrayNotHasKey('raw_image', $this->ofType($events, 'candidate')[0]);
        $this->assertDatabaseCount('fragment_projects', 0);
        $this->assertDatabaseCount('fragment_ai_runs', 0);
        $this->assertDatabaseCount('fragment_versions', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
        Http::assertNothingSent();
    }

    public function test_test_mode_can_work_on_a_saved_project_without_recording(): void
    {
        $project = $this->savedProject();

        $events = $this->events($this->postTool('admin.tool.ai.generate', ['project_id' => $project->id, 'count' => 1, 'test_mode' => '1']));

        $this->assertCount(1, $this->ofType($events, 'candidate'));
        $this->assertCount(2, $project->versions()->get());
        $this->assertDatabaseCount('fragment_ai_runs', 1);
    }

    public function test_a_project_generation_reads_the_stored_image_and_numbers_after_existing_versions(): void
    {
        $project = $this->savedProject();
        $this->fakeGemini();

        $events = $this->events($this->postTool('admin.tool.ai.generate', ['project_id' => $project->id, 'count' => 2, 'hint' => 'plus gros']));

        $this->assertSame([], $this->ofType($events, 'project'), 'No new project.');
        $this->assertDatabaseCount('fragment_projects', 1);
        $this->assertSame([1, 2, 3, 4], $project->versions()->pluck('number')->all());
        $this->assertSame('plus gros', $project->fresh()->hint);
        $this->assertSame(2, $project->runs()->count());
        Http::assertSent(fn (Request $request) => $this->isImageRequest($request) && $request->data()['input'][1]['type'] === 'image');
    }

    public function test_a_project_keeps_its_format_and_palette_even_archived(): void
    {
        $project = $this->savedProject();
        $this->palette->update(['is_active' => false]);
        $this->format->update(['is_tool_enabled' => false]);
        $this->fakeGemini();

        $events = $this->events($this->postTool('admin.tool.ai.generate', ['project_id' => $project->id, 'count' => 1, 'format_id' => 999, 'palette_id' => 999]));

        $this->assertCount(1, $this->ofType($events, 'candidate'));
        $this->assertCount(16, $this->ofType($events, 'candidate')[0]['grid']);
    }

    public function test_a_partly_failed_generation_keeps_the_received_versions(): void
    {
        $imageCalls = 0;
        $this->answerWith(function (Request $request) use (&$imageCalls) {
            if (! $this->isImageRequest($request)) {
                return Http::response($this->textInteraction(['candidates' => [['index' => 0, 'score' => 6, 'problems' => []]], 'preferred' => 0]));
            }

            return ++$imageCalls === 1
                ? Http::response(['error' => ['message' => 'Quota exceeded']], 429)
                : Http::response($this->imageInteraction());
        });

        $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 2])));

        $run = FragmentAiRun::sole();
        $this->assertSame(FragmentAiRun::STATUS_PARTIAL, $run->status);
        $this->assertCount(1, $run->warnings);
        $this->assertSame(1, FragmentVersion::count());
    }

    public function test_a_failed_generation_is_recorded_with_its_error(): void
    {
        $this->answerWith(fn () => Http::response(['error' => ['message' => 'bad']], 400));

        $this->events($this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1])));

        $run = FragmentAiRun::sole();
        $this->assertSame(FragmentAiRun::STATUS_FAILED, $run->status);
        $this->assertSame('Aucune image n\'a pu être générée.', $run->error);
        $this->assertSame(0, FragmentVersion::count());
    }

    public function test_a_refinement_is_saved_as_a_child_version_with_its_remarks(): void
    {
        $project = $this->savedProject();
        $parent = $project->versions()->first();
        $this->answerWith(fn (Request $request) => $this->isImageRequest($request)
            ? Http::response($this->imageInteraction($this->modelImage('#17100e')))
            : Http::response($this->textInteraction(['candidates' => [['index' => 0, 'score' => 8, 'problems' => ['RAS']]], 'preferred' => 0])));

        $events = $this->events($this->postTool('admin.tool.ai.refine', [
            'project_id' => $project->id,
            'version_id' => $parent->id,
            'grid' => json_encode($parent->grid),
            'remarks' => "L'œil gauche a disparu\nLe contour est cassé",
            'hint' => 'garder le chapeau',
        ]));

        $refined = $project->versions()->reorder('number', 'desc')->first();
        $this->assertSame(3, $refined->number);
        $this->assertSame(FragmentVersion::KIND_REFINEMENT, $refined->kind);
        $this->assertSame($parent->id, $refined->parent_id);
        $this->assertSame(8.0, $refined->score);
        Storage::disk('local')->assertExists($refined->raw_image_path);

        $result = $this->ofType($events, 'result')[0];
        $this->assertArrayNotHasKey('raw_image', $result);
        $this->assertSame($refined->id, $result['version']['id']);
        $this->assertSame(1, $result['version']['parentNumber']);

        $run = $project->runs()->reorder('id', 'desc')->first();
        $this->assertSame(FragmentAiRun::KIND_REFINE, $run->kind);
        $this->assertSame($parent->id, $run->version_id);
        $this->assertSame(["L'œil gauche a disparu", 'Le contour est cassé'], $run->parameters['remarks']);
        $this->assertSame('garder le chapeau', $run->parameters['hint']);
    }

    public function test_an_unchanged_refinement_adds_no_version(): void
    {
        $project = $this->savedProject();
        $version = $project->versions()->first();
        $this->answerWith(fn () => Http::response($this->imageInteraction()));

        $events = $this->events($this->postTool('admin.tool.ai.refine', [
            'project_id' => $project->id,
            'version_id' => $version->id,
            'grid' => json_encode($version->grid),
        ]));

        $this->assertTrue($this->ofType($events, 'result')[0]['unchanged']);
        $this->assertSame(2, $project->versions()->count());
        $this->assertSame(FragmentAiRun::STATUS_SUCCEEDED, $project->runs()->reorder('id', 'desc')->first()->status);
    }

    public function test_a_version_of_another_project_is_refused(): void
    {
        $project = $this->savedProject();
        $other = FragmentVersion::factory()->create(['grid' => $this->filledGrid('hetre')]);

        $this->postTool('admin.tool.ai.refine', [
            'project_id' => $project->id,
            'version_id' => $other->id,
            'grid' => json_encode($this->filledGrid('hetre')),
        ])->assertJsonValidationErrors('version_id');

        $this->assertSame(1, $project->runs()->count());
    }

    public function test_suggestions_are_recorded(): void
    {
        $project = $this->savedProject();
        $version = $project->versions()->first();
        $this->answerWith(fn () => Http::response($this->textInteraction(['suggestions' => [
            ['row' => 6, 'col' => 7, 'code' => '8', 'reason' => 'Ajouter un œil'],
        ]])));

        $this->postTool('admin.tool.ai.suggest', ['project_id' => $project->id, 'version_id' => $version->id, 'grid' => json_encode($version->grid)])->assertOk();

        $run = $project->runs()->reorder('id', 'desc')->first();
        $this->assertSame(FragmentAiRun::KIND_SUGGEST, $run->kind);
        $this->assertSame(FragmentAiRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame($version->id, $run->version_id);
        $this->assertSame([['row' => 5, 'col' => 6, 'essence_id' => $this->id('tulipier_noir'), 'reason' => 'Ajouter un œil']], $run->suggestions);
        $this->assertGreaterThan(0, $run->cost_usd);
    }

    public function test_failed_suggestions_are_recorded(): void
    {
        $project = $this->savedProject();
        $this->answerWith(fn () => Http::response(['error' => ['message' => 'down']], 500));

        $this->postTool('admin.tool.ai.suggest', ['project_id' => $project->id, 'grid' => json_encode($this->filledGrid('hetre'))])->assertStatus(502);

        $this->assertSame(FragmentAiRun::STATUS_FAILED, $project->runs()->reorder('id', 'desc')->first()->status);
    }

    public function test_suggestions_without_project_create_one(): void
    {
        $this->answerWith(fn () => Http::response($this->textInteraction(['suggestions' => []])));

        $response = $this->postTool('admin.tool.ai.suggest', $this->imagePayload(['grid' => json_encode($this->filledGrid('hetre'))]));

        $project = FragmentProject::sole();
        $response->assertOk()->assertJsonPath('project.id', $project->id);
        $this->assertSame(0, $project->versions()->count());
    }

    /* ---------------------------------------------------------------- Reopening */

    public function test_the_tool_reopens_a_project_with_its_versions(): void
    {
        $project = $this->savedProject();
        $version = $project->versions()->first();
        $version->update(['edits' => [[0, 0, $this->id('noyer')]], 'motif' => ['motif' => 'dots', 'density' => 'low', 'essence_id' => $this->id('poirier_alisier')]]);
        $project->update(['selected_version_id' => $version->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.tool.projects.show', $project));

        $response->assertOk();
        $config = $response->viewData('config');
        $this->assertSame($project->id, $config['project']['id']);
        $this->assertSame($version->id, $config['project']['selectedVersionId']);
        $this->assertSame(['x' => 0, 'y' => 0, 'width' => 160, 'height' => 160], $config['project']['crop']);
        $this->assertSame(route('admin.tool.projects.source', $project), $config['project']['sourceUrl']);
        $this->assertCount(2, $config['project']['versions']);
        $this->assertSame([[0, 0, $this->id('noyer')]], $config['project']['versions'][0]['edits']);
        $this->assertSame('dots', $config['project']['versions'][0]['motif']['motif']);
        $this->assertNull($this->actingAs($this->admin)->get(route('admin.tool.index'))->viewData('config')['project']);
    }

    public function test_a_project_with_archived_palette_and_format_still_opens(): void
    {
        $project = $this->savedProject();
        $this->palette->update(['is_active' => false]);
        $this->format->update(['is_tool_enabled' => false]);
        $active = $this->otherPalette();

        $config = $this->actingAs($this->admin)->get(route('admin.tool.projects.show', $project))->assertOk()->viewData('config');

        $this->assertEqualsCanonicalizing([$this->palette->id, $active->id], $config['palettes']->pluck('id')->all());
        $this->assertSame([$this->format->id], $config['shapes']->flatMap(fn (array $shape) => $shape['formats']->pluck('id'))->all());

        $config = $this->actingAs($this->admin)->get(route('admin.tool.index'))->viewData('config');
        $this->assertSame([$active->id], $config['palettes']->pluck('id')->all());
    }

    public function test_the_source_and_thumbnail_are_served(): void
    {
        $project = $this->savedProject();

        $this->actingAs($this->admin)->get(route('admin.tool.projects.source', $project))->assertOk()->assertHeader('Content-Type', 'image/png');
        $thumbnail = $this->actingAs($this->admin)->get(route('admin.tool.projects.thumbnail', $project))->assertOk();
        $this->assertSame([160, 160], array_slice(getimagesizefromstring($thumbnail->streamedContent()), 0, 2), 'A small source is not enlarged.');
    }

    /* ---------------------------------------------------------------- Autosave */

    public function test_the_name_and_chosen_version_are_saved(): void
    {
        $project = $this->savedProject();
        $version = $project->versions()->reorder('number', 'desc')->first();

        $this->actingAs($this->admin)
            ->patchJson(route('admin.tool.projects.update', $project), ['name' => 'Chat roux', 'selected_version_id' => $version->id])
            ->assertOk();

        $project->refresh();
        $this->assertSame('Chat roux', $project->name);
        $this->assertSame($version->id, $project->selected_version_id);
    }

    public function test_a_version_of_another_project_cannot_be_chosen(): void
    {
        $project = $this->savedProject();
        $other = FragmentVersion::factory()->create();

        $this->actingAs($this->admin)
            ->patchJson(route('admin.tool.projects.update', $project), ['selected_version_id' => $other->id, 'name' => ''])
            ->assertJsonValidationErrors(['selected_version_id', 'name']);
    }

    public function test_edits_and_motif_are_saved_per_version(): void
    {
        $project = $this->savedProject();
        [$first, $second] = $project->versions()->get();
        $edits = [[0, 0, $this->id('noyer')], [15, 15, $this->id('tulipier_noir')]];
        $motif = ['motif' => 'dots', 'density' => 'medium', 'essence_id' => $this->id('poirier_alisier')];

        $this->actingAs($this->admin)
            ->patchJson(route('admin.tool.projects.versions.update', [$project, $first]), ['edits' => $edits, 'motif' => $motif])
            ->assertOk();

        $this->assertSame($edits, $first->fresh()->edits);
        $this->assertSame($motif, $first->fresh()->motif);
        $this->assertNull($second->fresh()->edits);
    }

    public function test_invalid_edits_and_motifs_are_refused(): void
    {
        $project = $this->savedProject();
        $version = $project->versions()->first();
        $foreign = WoodTexture::factory()->create()->id;

        $this->actingAs($this->admin)
            ->patchJson(route('admin.tool.projects.versions.update', [$project, $version]), [
                'edits' => [[16, 0, $this->id('noyer')], [0, 0, $foreign], [1, 1]],
                'motif' => ['motif' => 'unknown', 'density' => 'low', 'essence_id' => $this->id('noyer')],
            ])
            ->assertJsonValidationErrors(['edits.0.0', 'edits.1.2', 'edits.2', 'motif.motif']);

        $this->assertNull($version->fresh()->edits);
    }

    public function test_a_version_is_only_reachable_through_its_project(): void
    {
        $project = $this->savedProject();
        $other = FragmentVersion::factory()->create();

        $this->actingAs($this->admin)
            ->patchJson(route('admin.tool.projects.versions.update', [$project, $other]), ['edits' => []])
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------- List */

    public function test_the_list_shows_projects_with_their_thumbnails(): void
    {
        $project = $this->savedProject();
        $project->update(['name' => 'Chat roux', 'selected_version_id' => $project->versions()->first()->id]);
        $empty = FragmentProject::factory()->create(['name' => 'Projet vide']);

        $response = $this->actingAs($this->admin)->get(route('admin.tool.projects.index'));

        $response->assertOk()
            ->assertSee('Chat roux')
            ->assertSee('Projet vide')
            ->assertSee('Bois naturels')
            ->assertSee(route('admin.tool.projects.thumbnail', $project))
            ->assertSee(route('admin.tool.projects.preview', $project))
            ->assertDontSee(route('admin.tool.projects.preview', $empty))
            ->assertSee('Aucune version choisie');
    }

    public function test_the_preview_is_the_final_grid_in_flat_colours(): void
    {
        $project = $this->savedProject();
        $version = $project->versions()->first();
        $version->update(['edits' => [[0, 0, $this->id('tulipier_noir')]]]);
        $project->update(['selected_version_id' => $version->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.tool.projects.preview', $project));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $image = imagecreatefromstring($response->getContent());
        $this->assertSame(16 * 6, imagesx($image));
        $this->assertSame(0x17100E, imagecolorat($image, 2, 2) & 0xFFFFFF, 'The edit is applied.');
    }

    public function test_the_preview_needs_a_chosen_version(): void
    {
        $project = $this->savedProject();

        $this->actingAs($this->admin)->get(route('admin.tool.projects.preview', $project))->assertNotFound();
    }

    public function test_deleting_a_project_removes_its_versions_and_files(): void
    {
        $project = $this->savedProject();
        $directory = $project->directory();

        $this->actingAs($this->admin)->delete(route('admin.tool.projects.destroy', $project))
            ->assertRedirect(route('admin.tool.projects.index'));

        $this->assertDatabaseCount('fragment_projects', 0);
        $this->assertDatabaseCount('fragment_versions', 0);
        $this->assertDatabaseCount('fragment_ai_runs', 0);
        Storage::disk('local')->assertMissing($directory);
    }

    public function test_projects_are_shared_and_reserved_to_admins(): void
    {
        $project = $this->savedProject();
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($otherAdmin)->get(route('admin.tool.projects.show', $project))->assertOk();
        $this->actingAs($otherAdmin)->get(route('admin.tool.projects.index'))->assertSee($project->name);
        $this->actingAs($customer)->get(route('admin.tool.projects.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer)->get(route('admin.tool.projects.source', $project))->assertRedirect(route('admin.login'));
    }
}
