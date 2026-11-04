<?php

namespace Tests\Feature\Account;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountAuthTest extends TestCase
{
    private const EMAIL = 'client@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['services.shopify.store_domain' => 'fragment.myshopify.com']);
    }

    public function test_customer_can_log_in(): void
    {
        $this->fakeLoginSuccess();

        $this->post(route('account.login'), $this->loginPayload())
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHas('shopify_customer_token', 'customer-token')
            ->assertSessionHas('shopify_customer_expires_at', '2030-01-01T00:00:00Z');
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $this->fakeLoginSuccess();
        $this->startSession();
        $sessionIdBeforeLogin = session()->getId();

        $this->post(route('account.login'), $this->loginPayload());

        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
    }

    public function test_wrong_credentials_are_rejected_without_flashing_the_password(): void
    {
        $this->fakeLoginFailure();

        $this->from(route('account.login'))
            ->post(route('account.login'), $this->loginPayload('wrong'))
            ->assertRedirect(route('account.login'))
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.'])
            ->assertSessionHasInput('email', self::EMAIL)
            ->assertSessionMissing('shopify_customer_token');

        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $this->fakeLoginFailure();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('account.login'), $this->loginPayload('wrong'));
        }

        $this->post(route('account.login'), $this->loginPayload())
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('shopify_customer_token');

        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        Http::assertSentCount(5);
    }

    public function test_login_lockout_is_case_insensitive_on_the_email(): void
    {
        $this->fakeLoginFailure();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('account.login'), ['email' => strtoupper(self::EMAIL), 'password' => 'wrong']);
        }

        $this->post(route('account.login'), $this->loginPayload())
            ->assertSessionHasErrors('email');

        Http::assertSentCount(5);
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
    }

    public function test_login_lockout_does_not_affect_other_emails(): void
    {
        $this->fakeLoginFailure();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('account.login'), $this->loginPayload('wrong'));
        }

        $this->post(route('account.login'), ['email' => 'other@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        Http::assertSentCount(6);
    }

    public function test_successful_login_resets_the_failed_attempts(): void
    {
        Http::fakeSequence()
            ->pushResponse($this->loginFailureResponse())
            ->pushResponse($this->loginFailureResponse())
            ->pushResponse($this->loginFailureResponse())
            ->pushResponse($this->loginFailureResponse())
            ->pushResponse($this->loginSuccessResponse())
            ->whenEmpty($this->loginFailureResponse());

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('account.login'), $this->loginPayload('wrong'));
        }
        $this->post(route('account.login'), $this->loginPayload())
            ->assertRedirect(route('account.dashboard'));

        session()->flush();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('account.login'), $this->loginPayload('wrong'))
                ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
        }
    }

    public function test_customer_can_register_and_session_is_regenerated(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['data' => ['customerCreate' => ['customer' => ['id' => 'gid://shopify/Customer/1'], 'customerUserErrors' => []]]])
            ->push($this->loginSuccessBody()),
        ]);
        $this->startSession();
        $sessionIdBeforeRegister = session()->getId();

        $this->post(route('account.register'), $this->registerPayload())
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHas('shopify_customer_token', 'customer-token');

        $this->assertNotSame($sessionIdBeforeRegister, session()->getId());
    }

    public function test_registration_is_limited_per_ip(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerCreate' => [
            'customer' => null,
            'customerUserErrors' => [['code' => 'TAKEN', 'field' => ['input', 'email'], 'message' => 'Email has already been taken']],
        ]]])]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('account.register'), $this->registerPayload("client{$attempt}@example.com"));
        }

        $this->post(route('account.register'), $this->registerPayload('client9@example.com'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        Http::assertSentCount(5);
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_password_reset_always_shows_the_same_message(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerRecover' => [
            'customerUserErrors' => [['code' => 'UNIDENTIFIED_CUSTOMER', 'field' => ['email'], 'message' => 'Could not find customer']],
        ]]])]);

        $this->post(route('account.password.reset'), ['email' => self::EMAIL])
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();
    }

    public function test_password_reset_is_limited_per_email(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerRecover' => ['customerUserErrors' => []]]])]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('account.password.reset'), ['email' => self::EMAIL])
                ->assertSessionHas('success');
        }

        $this->post(route('account.password.reset'), ['email' => strtoupper(self::EMAIL)])
            ->assertSessionHasErrors('email');

        Http::assertSentCount(3);
    }

    public function test_logout_invalidates_the_session_and_deletes_the_shopify_token(): void
    {
        Http::fake(['*' => Http::response(['data' => ['customerAccessTokenDelete' => ['deletedAccessToken' => 'customer-token', 'userErrors' => []]]])]);

        $this->withSession(['shopify_customer_token' => 'customer-token', 'shopify_customer_expires_at' => '2030-01-01T00:00:00Z']);
        $sessionIdBeforeLogout = session()->getId();
        $csrfTokenBeforeLogout = session()->token();

        $this->post(route('account.logout'))
            ->assertRedirect(route('account.login'))
            ->assertSessionMissing('shopify_customer_token')
            ->assertSessionMissing('shopify_customer_expires_at');

        $this->assertNotSame($sessionIdBeforeLogout, session()->getId());
        $this->assertNotSame($csrfTokenBeforeLogout, session()->token());
        Http::assertSent(fn (Request $request): bool => str_contains($request['query'], 'customerAccessTokenDelete')
            && $request['variables'] === ['customerAccessToken' => 'customer-token']);
    }

    /**
     * @return array{email: string, password: string}
     */
    private function loginPayload(string $password = 'secret123'): array
    {
        return ['email' => self::EMAIL, 'password' => $password];
    }

    /**
     * @return array{first_name: string, last_name: string, email: string, password: string, password_confirmation: string}
     */
    private function registerPayload(string $email = self::EMAIL): array
    {
        return [
            'first_name' => 'Camille',
            'last_name' => 'Martin',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }

    private function fakeLoginSuccess(): void
    {
        Http::fake(['*' => $this->loginSuccessResponse()]);
    }

    private function fakeLoginFailure(): void
    {
        Http::fake(['*' => $this->loginFailureResponse()]);
    }

    /**
     * @return array{data: array{customerAccessTokenCreate: array{customerAccessToken: array{accessToken: string, expiresAt: string}, customerUserErrors: array<int, mixed>}}}
     */
    private function loginSuccessBody(): array
    {
        return ['data' => ['customerAccessTokenCreate' => [
            'customerAccessToken' => ['accessToken' => 'customer-token', 'expiresAt' => '2030-01-01T00:00:00Z'],
            'customerUserErrors' => [],
        ]]];
    }

    private function loginSuccessResponse(): PromiseInterface
    {
        return Http::response($this->loginSuccessBody());
    }

    private function loginFailureResponse(): PromiseInterface
    {
        return Http::response(['data' => ['customerAccessTokenCreate' => [
            'customerAccessToken' => null,
            'customerUserErrors' => [],
        ]]]);
    }
}
