<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class DemoCatalogPurchaseGuard
{
    public function isPreviewProduct(Product $product): bool
    {
        return str_starts_with($product->slug, (string) config('demo_catalog.prefix', 'demo-').'kidoki-');
    }

    public function ensurePurchasable(Product $product): void
    {
        if ($this->isPreviewProduct($product)) {
            throw ValidationException::withMessages([
                'variant_id' => 'محصول نمایشی است و امکان خرید ندارد.',
            ]);
        }
    }

    /** @param array<int|string, mixed> $cart */
    public function ensureCartIsPurchasable(array $cart): void
    {
        if ($cart === []) {
            return;
        }

        $containsPreview = ProductVariant::query()
            ->whereIn('id', array_map('intval', array_keys($cart)))
            ->whereHas('product', fn ($products) => $products->where('slug', 'like', config('demo_catalog.prefix', 'demo-').'kidoki-%'))
            ->exists();

        if ($containsPreview) {
            throw ValidationException::withMessages([
                'cart' => 'محصول نمایشی در سبد خرید است و امکان ثبت سفارش ندارد.',
            ]);
        }
    }
}
