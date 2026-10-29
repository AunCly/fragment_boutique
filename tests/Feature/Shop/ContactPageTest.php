<?php

namespace Tests\Feature\Shop;

use App\Mail\ContactMessage;
use App\Rules\Turnstile;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    /** @var Closure(): mixed */
    private Closure $turnstileResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        config([
            'contact.recipients' => ['first@example.com', 'second@example.com'],
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);

        $this->turnstileResponse = fn () => Http::response(['success' => true]);
        Http::fake([Turnstile::VERIFY_URL => fn () => ($this->turnstileResponse)()]);
    }

    public function test_contact_page_is_displayed(): void
    {
        $this->get(route('shop.contact'))
            ->assertOk()
            ->assertViewIs('shop.contact')
            ->assertViewHas('formStartedAt')
            ->assertSee('name="website"', false)
            ->assertSee('data-sitekey="test-site-key"', false)
            ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);
    }

    public function test_message_is_sent_to_all_configured_recipients(): void
    {
        $this->post(route('shop.contact.send'), $this->validPayload())
            ->assertRedirect(route('shop.contact'))
            ->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail): bool {
            return $mail->hasTo('first@example.com')
                && $mail->hasTo('second@example.com')
                && $mail->hasReplyTo('jeanne@example.com', 'Jeanne Dupont')
                && $mail->senderName === 'Jeanne Dupont'
                && $mail->body === 'Bonjour, une question sur le kit Hibou.';
        });

        Http::assertSent(fn (Request $request): bool => $request->url() === Turnstile::VERIFY_URL
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token');
    }

    public function test_missing_turnstile_token_is_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['cf-turnstile-response']);

        $this->post(route('shop.contact.send'), $payload)
            ->assertSessionHasErrors(['cf-turnstile-response' => 'Merci de valider la vérification anti-robot.']);

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_failed_turnstile_verification_is_rejected(): void
    {
        $this->turnstileResponse = fn () => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->post(route('shop.contact.send'), $this->validPayload())
            ->assertSessionHasErrors(['cf-turnstile-response' => 'La vérification anti-robot a échoué, merci de réessayer.']);

        Mail::assertNothingSent();
    }

    public function test_unreachable_turnstile_api_is_rejected(): void
    {
        $this->turnstileResponse = fn () => throw new ConnectionException('Timeout');

        $this->post(route('shop.contact.send'), $this->validPayload())
            ->assertSessionHasErrors('cf-turnstile-response');

        Mail::assertNothingSent();
    }

    public function test_turnstile_server_error_is_rejected(): void
    {
        $this->turnstileResponse = fn () => Http::response([], 500);

        $this->post(route('shop.contact.send'), $this->validPayload())
            ->assertSessionHasErrors('cf-turnstile-response');

        Mail::assertNothingSent();
    }

    public function test_email_content_contains_sender_details(): void
    {
        $mail = new ContactMessage('Jeanne Dupont', 'jeanne@example.com', "Ligne 1\nLigne 2");

        $mail->assertSeeInHtml('Jeanne Dupont');
        $mail->assertSeeInHtml('jeanne@example.com');
        $mail->assertSeeInHtml('Ligne 1<br>', false);
        $mail->assertHasSubject('Nouveau message de contact — Jeanne Dupont');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post(route('shop.contact.send'), [
            'form_started_at' => $this->startedSecondsAgo(10),
        ])->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertNothingSent();
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->post(route('shop.contact.send'), $this->validPayload(['email' => 'pas-un-email']))
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_too_short_message_is_rejected(): void
    {
        $this->post(route('shop.contact.send'), $this->validPayload(['message' => 'Court']))
            ->assertSessionHasErrors('message');

        Mail::assertNothingSent();
    }

    public function test_filled_honeypot_is_silently_discarded(): void
    {
        $this->post(route('shop.contact.send'), $this->validPayload(['website' => 'https://spam.example']))
            ->assertRedirect(route('shop.contact'))
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_too_fast_submission_is_silently_discarded(): void
    {
        $this->post(route('shop.contact.send'), $this->validPayload(['form_started_at' => $this->startedSecondsAgo(0)]))
            ->assertRedirect(route('shop.contact'))
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_tampered_timestamp_is_silently_discarded(): void
    {
        $this->post(route('shop.contact.send'), $this->validPayload(['form_started_at' => '1700000000']))
            ->assertRedirect(route('shop.contact'));

        Mail::assertNothingSent();
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('shop.contact.send'), $this->validPayload())->assertRedirect();
        }

        $this->post(route('shop.contact.send'), $this->validPayload())->assertTooManyRequests();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Jeanne Dupont',
            'email' => 'jeanne@example.com',
            'message' => 'Bonjour, une question sur le kit Hibou.',
            'website' => '',
            'cf-turnstile-response' => 'valid-token',
            'form_started_at' => $this->startedSecondsAgo(10),
            ...$overrides,
        ];
    }

    private function startedSecondsAgo(int $seconds): string
    {
        return Crypt::encryptString((string) now()->subSeconds($seconds)->timestamp);
    }
}
