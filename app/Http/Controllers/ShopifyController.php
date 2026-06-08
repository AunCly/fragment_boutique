<?php

namespace App\Http\Controllers;

use App\Models\Format;
use App\Models\WoodTexture;
use App\Services\ShopifyAdminService;
use App\Services\ShopifyStorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopifyController extends Controller
{
    public function __construct(
        private ShopifyStorefrontService $storefront,
        private ShopifyAdminService $admin,
    ) {}

    public function index(): View
    {
        return view('shop.index');
    }

    public function products(): View
    {
        $products = $this->storefront->fetchProducts();

        return view('shop.products', compact('products'));
    }

    public function custom(): View
    {
        $woodTextures = WoodTexture::active()->ordered()->get()->map->toBuilderEntry()->values();
        $formats = Format::active()->ordered()->get()->map->toBuilderEntry()->values();

        return view('shop.custom', compact('woodTextures', 'formats'));
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:variant,custom'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.variantId' => ['required_if:items.*.type,variant', 'string'],
            'items.*.title' => ['required_if:items.*.type,custom', 'string', 'max:255'],
            'items.*.price' => ['required_if:items.*.type,custom', 'numeric', 'min:0'],
        ]);

        $invoiceUrl = $this->admin->createDraftOrder($validated['items']);

        return response()->json(['redirect' => $invoiceUrl]);
    }
}
