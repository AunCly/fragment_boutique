<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /**
     * @return array{email: string, password: string}
     */
    private function credentials(string $password = 'password'): array
    {
        return ['email' => $this->admin->email, 'password' => $password];
    }

    public function test_admin_can_log_in(): void
    {
        $this->post(route('admin.login'), $this->credentials())
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->from(route('admin.login'))
            ->post(route('admin.login'), $this->credentials('wrong'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->assertGuest();
    }

    public function test_non_admin_user_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('admin.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Accès non autorisé.']);

        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login'), $this->credentials('wrong'));
        }

        $this->post(route('admin.login'), $this->credentials())
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
    }

    public function test_lockout_is_case_insensitive_on_the_email(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login'), ['email' => strtoupper($this->admin->email), 'password' => 'wrong']);
        }

        $this->post(route('admin.login'), $this->credentials());

        $this->assertGuest();
    }

    public function test_lockout_expires_after_a_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login'), $this->credentials('wrong'));
        }

        $this->travel(61)->seconds();

        $this->post(route('admin.login'), $this->credentials())
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_successful_login_resets_the_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('admin.login'), $this->credentials('wrong'));
        }
        $this->post(route('admin.login'), $this->credentials());
        $this->post(route('admin.logout'));

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('admin.login'), $this->credentials('wrong'));
        }

        $this->post(route('admin.login'), $this->credentials())
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_route_is_throttled_by_ip(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post(route('admin.login'), ['email' => "user{$attempt}@example.com", 'password' => 'wrong']);
        }

        $this->post(route('admin.login'), $this->credentials())->assertTooManyRequests();
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Intrus',
            'email' => 'intrus@example.com',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }
}
