<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopifyCustomerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = session('shopify_customer_token');
        $expiresAt = session('shopify_customer_expires_at');

        if (! $token || ($expiresAt && now()->isAfter($expiresAt))) {
            $request->session()->forget(['shopify_customer_token', 'shopify_customer_expires_at']);

            return redirect()->route('account.login');
        }

        return $next($request);
    }
}
