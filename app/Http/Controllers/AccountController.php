<?php

namespace App\Http\Controllers;

use App\Services\ShopifyCustomerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
    /** Failed login attempts allowed per email and IP before the login is locked. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_LOCKOUT_SECONDS = 60;

    /** Account creations allowed per IP within the decay window. */
    private const MAX_REGISTER_ATTEMPTS = 5;

    private const REGISTER_DECAY_SECONDS = 600;

    /** Reset emails allowed per address within the decay window (prevents mail-bombing a customer). */
    private const MAX_PASSWORD_RESET_ATTEMPTS = 3;

    private const PASSWORD_RESET_DECAY_SECONDS = 600;

    public function __construct(private ShopifyCustomerService $shopify) {}

    public function showLogin(): View|RedirectResponse
    {
        if (session('shopify_customer_token')) {
            return redirect()->route('account.dashboard');
        }

        return view('account.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'account-login:'.$this->normalizedEmail($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return $this->tooManyAttempts($throttleKey, 'email');
        }

        try {
            $tokenData = $this->shopify->createAccessToken($validated['email'], $validated['password']);
        } catch (RuntimeException $e) {
            RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);

            return back()->withInput($request->except('password'))->withErrors(['email' => $e->getMessage()]);
        }

        RateLimiter::clear($throttleKey);
        $this->startCustomerSession($request, $tokenData);

        return redirect()->route('account.dashboard');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (session('shopify_customer_token')) {
            return redirect()->route('account.dashboard');
        }

        return view('account.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $throttleKey = 'account-register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_REGISTER_ATTEMPTS)) {
            return $this->tooManyAttempts($throttleKey, 'email');
        }

        RateLimiter::hit($throttleKey, self::REGISTER_DECAY_SECONDS);

        try {
            $tokenData = $this->shopify->createCustomer(
                $validated['first_name'],
                $validated['last_name'],
                $validated['email'],
                $validated['password'],
            );
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['email' => $e->getMessage()]);
        }

        $this->startCustomerSession($request, $tokenData);

        return redirect()->route('account.dashboard');
    }

    public function showActivate(Request $request): View|RedirectResponse
    {
        if (session('shopify_customer_token')) {
            return redirect()->route('account.dashboard');
        }

        $activationUrl = $request->query('url');

        if (! is_string($activationUrl) || ! filter_var($activationUrl, FILTER_VALIDATE_URL)) {
            return redirect()->route('account.login')
                ->withErrors(['email' => 'Ce lien d\'activation est invalide ou a expiré.']);
        }

        return view('account.activate', compact('activationUrl'));
    }

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'activation_url' => ['required', 'url'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $tokenData = $this->shopify->activateCustomerByUrl($validated['activation_url'], $validated['password']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        $this->startCustomerSession($request, $tokenData);

        return redirect()->route('account.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = session('shopify_customer_token');

        if ($token) {
            try {
                $this->shopify->deleteAccessToken($token);
            } catch (\Exception) {
                // Token may already be expired
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('account.login');
    }

    public function showForgotPassword(): View|RedirectResponse
    {
        if (session('shopify_customer_token')) {
            return redirect()->route('account.dashboard');
        }

        return view('account.forgot-password');
    }

    public function sendPasswordReset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $throttleKey = 'account-password-reset:'.$this->normalizedEmail($validated['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PASSWORD_RESET_ATTEMPTS)) {
            return $this->tooManyAttempts($throttleKey, 'email');
        }

        RateLimiter::hit($throttleKey, self::PASSWORD_RESET_DECAY_SECONDS);

        try {
            $this->shopify->sendPasswordReset($validated['email']);
        } catch (\Exception) {
            // Don't expose whether the email exists
        }

        return back()->with('success', 'Si un compte existe avec cette adresse, un email de réinitialisation a été envoyé.');
    }

    public function showDashboard(): View|RedirectResponse
    {
        try {
            $customer = $this->shopify->getCustomer(session('shopify_customer_token'));
        } catch (RuntimeException) {
            return $this->invalidSession();
        }

        return view('account.dashboard', compact('customer'));
    }

    public function showOrder(string $orderNumber): View|RedirectResponse
    {
        try {
            $customer = $this->shopify->getCustomer(session('shopify_customer_token'));
        } catch (RuntimeException) {
            return $this->invalidSession();
        }

        $order = collect($customer['orders'])->first(fn ($o) => (string) $o['orderNumber'] === $orderNumber);

        if (! $order) {
            abort(404);
        }

        return view('account.order', compact('order', 'customer'));
    }

    public function downloadInvoice(string $orderNumber): Response|RedirectResponse
    {
        try {
            $customer = $this->shopify->getCustomer(session('shopify_customer_token'));
        } catch (RuntimeException) {
            return $this->invalidSession();
        }

        $order = collect($customer['orders'])->first(fn ($o) => (string) $o['orderNumber'] === $orderNumber);

        if (! $order) {
            abort(404);
        }

        $pdf = Pdf::loadView('invoices.order', compact('order', 'customer'));

        return $pdf->download("facture-{$order['orderNumber']}.pdf");
    }

    public function showProfile(): View|RedirectResponse
    {
        try {
            $customer = $this->shopify->getCustomer(session('shopify_customer_token'));
        } catch (RuntimeException) {
            return $this->invalidSession();
        }

        return view('account.profile', compact('customer'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'firstName' => $validated['first_name'],
            'lastName' => $validated['last_name'],
            'email' => $validated['email'],
        ];

        $isChangingPassword = ! empty($validated['password']);
        if ($isChangingPassword) {
            $updateData['password'] = $validated['password'];
        }

        try {
            $this->shopify->updateCustomer(session('shopify_customer_token'), $updateData);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        if ($isChangingPassword) {
            $request->session()->forget(['shopify_customer_token', 'shopify_customer_expires_at']);

            return redirect()->route('account.login')->with('success', 'Mot de passe mis à jour. Veuillez vous reconnecter.');
        }

        return back()->with('success', 'Informations mises à jour.');
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address1' => ['required', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        try {
            $customer = $this->shopify->getCustomer(session('shopify_customer_token'));
            $existingAddressId = $customer['defaultAddress']['id'] ?? null;

            $this->shopify->upsertDefaultAddress(session('shopify_customer_token'), $existingAddressId, $validated);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['address1' => $e->getMessage()]);
        }

        return back()->with('success_address', 'Adresse mise à jour.');
    }

    /**
     * Store the Shopify token in a fresh session id to prevent session fixation.
     *
     * @param  array{token: string, expiresAt: string}  $tokenData
     */
    private function startCustomerSession(Request $request, array $tokenData): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            'shopify_customer_token' => $tokenData['token'],
            'shopify_customer_expires_at' => $tokenData['expiresAt'],
        ]);
    }

    private function normalizedEmail(string $email): string
    {
        return Str::transliterate(Str::lower($email));
    }

    private function tooManyAttempts(string $throttleKey, string $field): RedirectResponse
    {
        $seconds = RateLimiter::availableIn($throttleKey);

        return back()
            ->withInput(request()->only('email'))
            ->withErrors([$field => "Trop de tentatives. Réessayez dans {$seconds} secondes."]);
    }

    private function invalidSession(): RedirectResponse
    {
        session()->forget(['shopify_customer_token', 'shopify_customer_expires_at']);

        return redirect()->route('account.login');
    }
}
