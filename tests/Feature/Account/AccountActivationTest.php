<?php

namespace Tests\Feature\Account;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    private const ACTIVATION_URL = 'https://fragment.myshopify.com/account/activate/123/abc';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['services.shopify.store_domain' => 'fragment.myshopify.com']);
    }

    public function test_activation_form_is_displayed_with_activation_url(): void
    {
        $this->get(route('account.activate', ['url' => self::ACTIVATION_URL]))
            ->assertOk()
            ->assertViewIs('account.activate')
            ->assertSee('value="'.self::ACTIVATION_URL.'"', false);
    }

    public function test_activation_form_redirects_to_login_without_valid_url(): void
    {
        $this->get(route('account.activate'))
            ->assertRedirect(route('account.login'))
            ->assertSessionHasErrors('email');

        $this->get(route('account.activate', ['url' => 'not-a-url']))
            ->assertRedirect(route('account.login'))
            ->assertSessionHasErrors('email');
    }

    public function test_activation_form_redirects_logged_in_customer_to_dashboard(): void
    {
        $this->withSession(['shopify_customer_token' => 'token'])
            ->get(route('account.activate', ['url' => self::ACTIVATION_URL]))
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_customer_is_activated_and_logged_in(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerActivateByUrl' => [
            'customerAccessToken' => ['accessToken' => 'new-token', 'expiresAt' => '2030-01-01T00:00:00Z'],
            'customerUserErrors' => [],
        ]]])]);

        $this->post(route('account.activate'), $this->validPayload())
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHas('shopify_customer_token', 'new-token')
            ->assertSessionHas('shopify_customer_expires_at', '2030-01-01T00:00:00Z');

        Http::assertSent(fn (Request $request): bool => str_contains($request['query'], 'customerActivateByUrl')
            && $request['variables'] === ['activationUrl' => self::ACTIVATION_URL, 'password' => 'secret123']);
    }

    public function test_invalid_activation_token_shows_error(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerActivateByUrl' => [
            'customerAccessToken' => null,
            'customerUserErrors' => [['code' => 'TOKEN_INVALID', 'field' => null, 'message' => 'Invalid token']],
        ]]])]);

        $this->post(route('account.activate'), $this->validPayload())
            ->assertSessionHasErrors(['password' => 'Ce lien d\'activation est invalide ou a expiré.'])
            ->assertSessionMissing('shopify_customer_token');
    }

    public function test_already_activated_account_shows_error(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerActivateByUrl' => [
            'customerAccessToken' => null,
            'customerUserErrors' => [['code' => 'ALREADY_ENABLED', 'field' => null, 'message' => 'Already enabled']],
        ]]])]);

        $this->post(route('account.activate'), $this->validPayload())
            ->assertSessionHasErrors(['password' => 'Ce compte est déjà activé. Vous pouvez vous connecter.']);
    }

    public function test_activation_requires_confirmed_password_of_eight_characters(): void
    {
        Http::fake();

        $this->post(route('account.activate'), [
            'activation_url' => self::ACTIVATION_URL,
            'password' => 'short',
            'password_confirmation' => 'other',
        ])->assertSessionHasErrors('password');

        Http::assertNothingSent();
    }

    public function test_register_with_guest_checkout_email_explains_activation(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerCreate' => [
            'customer' => null,
            'customerUserErrors' => [['code' => 'CUSTOMER_DISABLED', 'field' => ['input'], 'message' => 'We have sent an email']],
        ]]])]);

        $this->post(route('account.register'), [
            'first_name' => 'Jeanne',
            'last_name' => 'Dupont',
            'email' => 'jeanne@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors([
            'email' => 'Un compte existe déjà pour cette adresse suite à une commande. Nous venons de vous envoyer un email pour l\'activer.',
        ]);
    }

    /**
     * @return array{activation_url: string, password: string, password_confirmation: string}
     */
    private function validPayload(): array
    {
        return [
            'activation_url' => self::ACTIVATION_URL,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }
}
