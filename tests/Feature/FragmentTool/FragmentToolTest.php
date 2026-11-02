<?php

namespace Tests\Feature\FragmentTool;

use App\Models\Format;
use App\Models\Palette;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FragmentTool\Concerns\BuildsToolFixtures;
use Tests\TestCase;

class FragmentToolTest extends TestCase
{
    use BuildsToolFixtures;
    use RefreshDatabase;

    private User $admin;

    private Palette $palette;

    private Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        config(['services.gemini.key' => null]);

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->palette = $this->createToolPalette();
        $this->format = $this->createToolFormat(16, 16);
    }

    /**
     * 160 × 160 PNG: merisier square on a hêtre background.
     */
    private function sourceImage(): UploadedFile
    {
        return $this->uploadedPng($this->pixelArtPng($this->subjectHexGrid(16, 16, '#d6b47c', '#c26d2d'), 10));
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
            'image' => $this->sourceImage(),
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

    private function id(string $slug): int
    {
        return $this->essenceId($this->palette, $slug);
    }

    /**
     * @return list<list<int>>
     */
    private function subjectGrid(string $subjectSlug = 'merisier'): array
    {
        $cells = [];
        for ($r = 0; $r < 16; $r++) {
            for ($c = 0; $c < 16; $c++) {
                $cells[$r][$c] = $r >= 4 && $c >= 4 && $r < 12 && $c < 12 ? $this->id($subjectSlug) : $this->id('hetre');
            }
        }

        return $cells;
    }

    /* ---------------------------------------------------------------- Page */

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.tool.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.tool.convert'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_formats_by_shape_and_palettes(): void
    {
        $this->createToolFormat(32, 16);
        $this->createToolFormat(24, 48);
        Format::factory()->create(['cols' => 99, 'rows' => 99]);
        Palette::factory()->create(['name' => 'Palette vide']);

        $response = $this->actingAs($this->admin)->get(route('admin.tool.index'));

        $response->assertOk()
            ->assertViewIs('admin.tool.index')
            ->assertSeeInOrder(['Carré', '16 × 16', 'Paysage', '32 × 16', 'Portrait', '24 × 48'])
            ->assertSee('Bois naturels')
            ->assertDontSee('99 × 99')
            ->assertDontSee('Palette vide')
            ->assertSee('Redessin IA désactivé');

        $config = $response->viewData('config');
        $this->assertFalse($config['ai']['enabled']);
        $this->assertSame($this->id('hetre'), $config['palettes'][0]['defaultBackgroundId']);
        $this->assertSame($this->id('sycomore'), $config['palettes'][0]['haloId']);
    }

    public function test_page_explains_what_is_missing_without_palette(): void
    {
        $this->palette->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.tool.index'))
            ->assertOk()
            ->assertSee('Il faut au moins un format disponible dans l\'outil', false);
    }

    public function test_ai_is_enabled_with_an_api_key(): void
    {
        config(['services.gemini.key' => 'test-key']);

        $config = $this->actingAs($this->admin)->get(route('admin.tool.index'))->viewData('config');

        $this->assertTrue($config['ai']['enabled']);
    }

    /* ---------------------------------------------------------------- Classic conversion */

    public function test_classic_conversion_returns_the_grid(): void
    {
        $response = $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'ciede2000']));

        $response->assertOk();
        $this->assertSame($this->subjectGrid(), $response->json('grid'));
        $this->assertSame(64, $response->json('counts.'.$this->id('merisier')));
    }

    public function test_conversion_rejects_unknown_methods(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'magic', 'matching' => 'tone']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reduction');
    }

    public function test_crop_must_have_the_format_ratio(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone', 'crop_height' => 80]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('crop');
    }

    public function test_crop_must_stay_inside_the_image(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone', 'crop_x' => 50]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('crop');
    }

    public function test_crop_smaller_than_the_grid_is_refused(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone', 'crop_width' => 10, 'crop_height' => 10]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('crop');
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload([
            'reduction' => 'mode',
            'matching' => 'tone',
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_a_fake_png_is_refused_by_its_content(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload([
            'reduction' => 'mode',
            'matching' => 'tone',
            'image' => UploadedFile::fake()->createWithContent('piege.png', 'GIF89a not a png'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_too_many_pixels_are_refused_before_decoding(): void
    {
        config(['fragment_tool.max_pixels' => 1000]);

        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Image trop grande (nombre de pixels).']);
    }

    public function test_format_must_be_enabled_in_the_tool(): void
    {
        $shopOnly = Format::factory()->create(['cols' => 16, 'rows' => 16]);

        $this->postTool('admin.tool.convert', $this->imagePayload(['format_id' => $shopOnly->id, 'reduction' => 'mode', 'matching' => 'tone']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format_id');
    }

    public function test_palette_must_be_active(): void
    {
        $this->palette->update(['is_active' => false]);

        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('palette_id');
    }

    public function test_palette_with_a_single_essence_is_refused(): void
    {
        $this->palette->woodTextures()->sync([$this->id('noyer')]);

        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('palette_id');
    }

    public function test_background_essence_must_belong_to_the_palette(): void
    {
        $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'tone', 'background_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('background_id');
    }

    public function test_rectangular_formats_are_converted(): void
    {
        $this->format = $this->createToolFormat(32, 16);

        $response = $this->postTool('admin.tool.convert', $this->imagePayload([
            'crop_y' => 40,
            'crop_height' => 80,
            'reduction' => 'contours',
            'matching' => 'tone',
        ]));

        $response->assertOk();
        $this->assertCount(16, $response->json('grid'));
        $this->assertCount(32, $response->json('grid.0'));
    }

    public function test_preview_returns_the_thirty_combinations(): void
    {
        $response = $this->postTool('admin.tool.preview', $this->imagePayload());

        $response->assertOk();
        $this->assertCount(30, $response->json('grids'));
    }

    public function test_concurrent_users_do_not_share_state(): void
    {
        $first = $this->postTool('admin.tool.convert', $this->imagePayload(['reduction' => 'mode', 'matching' => 'ciede2000']))->json('grid');

        $otherImage = $this->uploadedPng($this->pixelArtPng($this->subjectHexGrid(16, 16, '#d6b47c', '#17100e'), 10));
        $second = $this->postTool('admin.tool.convert', $this->imagePayload(['image' => $otherImage, 'reduction' => 'mode', 'matching' => 'ciede2000']))->json('grid');

        $this->assertSame($this->subjectGrid('merisier'), $first);
        $this->assertSame($this->subjectGrid('tulipier_noir'), $second);
    }

    /* ---------------------------------------------------------------- Motifs and renders */

    public function test_motifs_are_applied_to_the_background(): void
    {
        $this->format = $this->createToolFormat(32, 32);
        $grid = array_fill(0, 32, array_fill(0, 32, $this->id('hetre')));

        $response = $this->postTool('admin.tool.motifs', [
            'format_id' => $this->format->id,
            'palette_id' => $this->palette->id,
            'grid' => json_encode($grid),
            'motif' => 'dots',
            'density' => 'medium',
            'essence_id' => $this->id('poirier_alisier'),
        ]);

        $response->assertOk();
        $this->assertContains($this->id('poirier_alisier'), array_merge(...$response->json('grid')));
    }

    public function test_motifs_refuse_invalid_parameters(): void
    {
        $base = [
            'format_id' => $this->format->id,
            'palette_id' => $this->palette->id,
            'grid' => json_encode($this->subjectGrid()),
            'motif' => 'dots',
            'density' => 'low',
            'essence_id' => $this->id('poirier_alisier'),
        ];

        $this->postTool('admin.tool.motifs', ['motif' => 'licorne'] + $base)->assertJsonValidationErrors('motif');
        $this->postTool('admin.tool.motifs', ['density' => 'forte'] + $base)->assertJsonValidationErrors('density');
        $this->postTool('admin.tool.motifs', ['essence_id' => 999999] + $base)->assertJsonValidationErrors('essence_id');
        $this->postTool('admin.tool.motifs', ['grid' => '[[1,2]]'] + $base)->assertJsonValidationErrors('grid');
    }

    public function test_render_returns_the_three_pngs(): void
    {
        foreach (['image' => 'chat_16x16.png', 'wood' => 'chat_16x16_bois.png', 'plan' => 'chat_16x16_plan.png'] as $kind => $filename) {
            $response = $this->postTool('admin.tool.render', [
                'format_id' => $this->format->id,
                'palette_id' => $this->palette->id,
                'grid' => json_encode($this->subjectGrid()),
                'kind' => $kind,
                'name' => 'chat',
            ]);

            $response->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->assertStringContainsString($filename, $response->headers->get('Content-Disposition'));
            $this->assertNotFalse(imagecreatefromstring($response->getContent()));
        }
    }

    public function test_render_refuses_an_unknown_kind(): void
    {
        $this->postTool('admin.tool.render', [
            'format_id' => $this->format->id,
            'palette_id' => $this->palette->id,
            'grid' => json_encode($this->subjectGrid()),
            'kind' => 'pdf',
        ])->assertJsonValidationErrors('kind');
    }

    /* ---------------------------------------------------------------- AI */

    public function test_ai_routes_are_unavailable_without_api_key(): void
    {
        Http::fake();

        $this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 1]))
            ->assertStatus(503)
            ->assertJson(['message' => 'Redessin IA désactivé : aucune clé GEMINI_API_KEY configurée.']);
        $this->postTool('admin.tool.ai.suggest', $this->imagePayload(['grid' => json_encode($this->subjectGrid())]))->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_generate_count_must_be_1_2_or_4(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake();

        $this->postTool('admin.tool.ai.generate', $this->imagePayload(['count' => 3]))->assertJsonValidationErrors('count');

        Http::assertNothingSent();
    }
}
