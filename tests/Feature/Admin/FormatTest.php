<?php

namespace Tests\Feature\Admin;

use App\Models\Format;
use App\Models\User;
use Database\Seeders\FormatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_create_format_available_in_the_tool(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.formats.store'), [
                'slug' => 'portrait-m',
                'label' => '24 × 48 cm',
                'cols' => 24,
                'rows' => 48,
                'sort_order' => 10,
                'is_tool_enabled' => '1',
            ])
            ->assertRedirect(route('admin.formats.index'));

        $format = Format::sole();
        $this->assertTrue($format->is_tool_enabled);
        $this->assertFalse($format->is_active);
        $this->assertSame('portrait', $format->shape);
    }

    public function test_admin_can_remove_format_from_the_tool(): void
    {
        $format = Format::factory()->forTool()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.formats.update', $format), [
                'slug' => $format->slug,
                'label' => $format->label,
                'cols' => $format->cols,
                'rows' => $format->rows,
                'sort_order' => $format->sort_order,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.formats.index'));

        $format->refresh();
        $this->assertFalse($format->is_tool_enabled);
        $this->assertTrue($format->is_active);
    }

    public function test_index_flags_tool_formats(): void
    {
        Format::factory()->forTool()->create(['label' => '48 × 24 cm']);

        $this->actingAs($this->admin)
            ->get(route('admin.formats.index'))
            ->assertOk()
            ->assertSee('48 × 24 cm')
            ->assertSee('Outil');
    }

    public function test_for_tool_scope_only_returns_tool_formats(): void
    {
        $toolFormat = Format::factory()->forTool()->create();
        Format::factory()->create();

        $this->assertSame([$toolFormat->id], Format::forTool()->pluck('id')->all());
    }

    public function test_seeder_provides_the_eleven_tool_formats_by_shape(): void
    {
        $this->seed(FormatSeeder::class);

        $dimensions = Format::forTool()->get()
            ->groupBy('shape')
            ->map(fn ($formats) => $formats->map(fn (Format $format) => "{$format->cols}x{$format->rows}")->sort()->values()->all());

        $this->assertEqualsCanonicalizing(['16x16', '32x32', '64x64'], $dimensions['square']);
        $this->assertEqualsCanonicalizing(['32x16', '64x32', '96x48', '48x24'], $dimensions['landscape']);
        $this->assertEqualsCanonicalizing(['16x32', '32x64', '24x48', '48x96'], $dimensions['portrait']);
    }

    public function test_seeder_keeps_tool_only_formats_out_of_the_shop_builder(): void
    {
        $this->seed(FormatSeeder::class);

        $this->assertSame(6, Format::active()->count());
    }
}
