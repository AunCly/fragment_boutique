<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

class Turnstile implements ValidationRule
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify the Cloudflare Turnstile token against the siteverify API.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $failureMessage = 'La vérification anti-robot a échoué, merci de réessayer.';

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('Turnstile verification unreachable', ['error' => $exception->getMessage()]);
            $fail($failureMessage);

            return;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::info('Turnstile verification failed', ['error_codes' => $response->json('error-codes')]);
            $fail($failureMessage);
        }
    }
}
