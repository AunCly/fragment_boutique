<?php

namespace App\Http\Requests;

use App\Rules\Turnstile;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;

class ContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'cf-turnstile-response' => ['required', 'string', new Turnstile],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom et prénom',
            'email' => 'adresse email',
            'message' => 'message',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cf-turnstile-response.required' => 'Merci de valider la vérification anti-robot.',
        ];
    }

    /**
     * Detect bots via the honeypot field and the minimum time spent on the form.
     */
    public function isLikelyBot(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        try {
            $startedAt = (int) Crypt::decryptString((string) $this->input('form_started_at'));
        } catch (DecryptException) {
            return true;
        }

        return now()->timestamp - $startedAt < config('contact.minimum_submit_seconds');
    }
}
