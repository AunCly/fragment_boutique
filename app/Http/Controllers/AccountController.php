<?php

namespace App\Http\Controllers;

use App\Services\ShopifyCustomerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
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

        try {
            $tokenData = $this->shopify->createAccessToken($validated['email'], $validated['password']);
            session([
                'shopify_customer_token' => $tokenData['token'],
                'shopify_customer_expires_at' => $tokenData['expiresAt'],
            ]);

            return redirect()->route('account.dashboard');
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }
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

        try {
            $tokenData = $this->shopify->createCustomer(
                $validated['first_name'],
                $validated['last_name'],
                $validated['email'],
                $validated['password'],
            );
            session([
                'shopify_customer_token' => $tokenData['token'],
                'shopify_customer_expires_at' => $tokenData['expiresAt'],
            ]);

            return redirect()->route('account.dashboard');
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }
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

        $request->session()->forget(['shopify_customer_token', 'shopify_customer_expires_at']);

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
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $this->shopify->sendPasswordReset($request->email);
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

    private function invalidSession(): RedirectResponse
    {
        session()->forget(['shopify_customer_token', 'shopify_customer_expires_at']);

        return redirect()->route('account.login');
    }
}
