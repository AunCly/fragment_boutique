<?php

namespace App\Http\Controllers;

use App\Models\Faq;
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
        $fragments = $this->storefront->fetchProducts(4);

        return view('shop.index', compact('fragments'));
    }

    public function products(): View
    {
        $products = $this->storefront->fetchProducts();
        $filterGroups = $this->buildFilterGroups($products);

        $products = collect($products)
            ->map(fn (array $product) => [
                ...$product,
                'priceFormatted' => number_format((float) $product['price'], 0, ',', ' ').' €',
            ])
            ->values()
            ->all();

        return view('shop.products', compact('products', 'filterGroups'));
    }

    /**
     * @param  array<int, array{tags: array<string, string>}>  $products
     * @return array<int, array{name: string, values: array<int, string>}>
     */
    private function buildFilterGroups(array $products): array
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product['tags'] as $name => $value) {
                $values[$name][$value] = true;
            }
        }

        return collect($values)
            ->map(fn (array $tagValues, string $name) => [
                'name' => $name,
                'values' => array_keys($tagValues),
            ])
            ->values()
            ->all();
    }

    public function howItWorks(): View
    {
        $faqs = Faq::active()->ordered()->get();

        return view('shop.how-it-works', compact('faqs'));
    }

    public function workshop(): View
    {
        return view('shop.workshop');
    }

    public function gallery(): View
    {
        $images = [
            ['path' => 'resources/images/gallery/gallery-1-Cuy0LPkR.jpg', 'alt' => 'Un Fragment assemblé, installé dans un intérieur'],
            ['path' => 'resources/images/gallery/product-owl-D5LQ9sc5.jpg', 'alt' => 'Un Fragment assemblé, installé dans un intérieur'],
            ['path' => 'resources/images/gallery/gallery-2-Dc8Qmn3J.jpg', 'alt' => 'Un Fragment assemblé, installé dans un intérieur'],
            ['path' => 'resources/images/gallery/product-mountains-C2h_MNwy.jpg', 'alt' => 'Un Fragment assemblé, installé dans un intérieur'],
            ['path' => 'resources/images/gallery/gallery-3-CCzQGLN0.jpg', 'alt' => 'Un Fragment assemblé, installé dans un intérieur'],
            ['path' => 'resources/images/gallery/product-geo2-BEs2mhOM.jpg', 'alt' => 'Un Fragment assemblé, installé dans un intérieur'],
        ];

        return view('shop.gallery', compact('images'));
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
