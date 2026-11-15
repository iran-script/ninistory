<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class ProductVariantSkuService
{
    public function generate(Product $product, string $color, string $size): string
    {
        $productCode = str_pad((string) $product->getKey(), 6, '0', STR_PAD_LEFT);
        $colorCode = $this->code($color, 3);
        $sizeCode = $this->code($size, 4);
        $base = "KD-{$productCode}-{$colorCode}-{$sizeCode}";
        $candidate = $base;
        $suffix = 1;
        while (ProductVariant::query()->where('sku', $candidate)->exists()) {
            $candidate = $base.'-'.str_pad((string) $suffix++, 2, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    public function create(Product $product, array $attributes): ProductVariant
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $attributes['sku'] = filled($attributes['sku'] ?? null) ? strtoupper(trim((string) $attributes['sku'])) : $this->generate($product, $attributes['color_name'], $attributes['size']);
            try {
                return $product->variants()->create($attributes);
            } catch (QueryException $exception) {
                if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw $exception;
                }
                $attributes['sku'] = null;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique variant SKU.');
    }

    private function code(string $value, int $length): string
    {
        $normalized = strtr(Str::upper(trim($value)), ['آ' => 'A', 'ا' => 'A', 'ی' => 'Y', 'ي' => 'Y', 'ک' => 'K', 'ك' => 'K', 'ژ' => 'ZH', 'چ' => 'CH', 'پ' => 'P', 'گ' => 'G', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?: 'X';

        return Str::substr($normalized, 0, $length);
    }
}
