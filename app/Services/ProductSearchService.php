<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSearchService
{
    public function normalize(string $query): string
    {
        $query = strtr($query, [
            "\u{064A}" => "\u{06CC}", "\u{0649}" => "\u{06CC}", "\u{0643}" => "\u{06A9}",
            "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
            "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
            "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
            "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
            "\u{200C}" => ' ', "\u{200D}" => ' ',
        ]);
        $query = preg_replace('/[^\p{L}\p{N}\s_-]+/u', ' ', $query) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', Str::lower($query)));
    }

    /** @return array<int, string> */
    public function searchVariants(string $query): array
    {
        $normalized = $this->normalize($query);
        $variants = [$normalized, strtr($normalized, ["\u{06CC}" => "\u{064A}", "\u{06A9}" => "\u{0643}"])];

        return array_values(array_unique(array_filter($variants)));
    }

    public function visibleQuery(): Builder
    {
        $query = Product::query()->with([
            'category:id,slug,name',
            'images:id,product_id,path,width,height,alt_text,sort_order,is_cover',
            'variants' => fn (HasMany $variants): HasMany => $variants->select(['id', 'product_id', 'sku', 'size', 'color_name', 'color_hex', 'price_toman', 'compare_at_price_toman', 'stock_quantity', 'reserved_quantity', 'is_active', 'archived_at'])->where('is_active', true)->whereNull('archived_at'),
        ])->where('is_active', true)->whereHas('variants', fn (Builder $variants): Builder => $variants->where('is_active', true)->whereNull('archived_at'));

        if (app()->environment('production')) {
            $query->where('slug', 'not like', (string) config('demo_catalog.prefix', 'demo-').'%')->where('merchant_verification_status', 'approved')->where('content_review_status', 'approved');
        }

        return $query;
    }

    public function applyTextSearch(Builder $query, string $search): Builder
    {
        $variants = $this->searchVariants($search);
        if ($variants === []) {
            return $query;
        }
        $query->where(function (Builder $products) use ($variants): void {
            foreach ($variants as $variant) {
                $like = '%'.$variant.'%';
                $products->orWhere('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('variants', fn (Builder $variant): Builder => $variant->where('is_active', true)->whereNull('archived_at')->where('sku', 'like', $like))
                    ->orWhereHas('category', fn (Builder $category): Builder => $category->where('name', 'like', $like));
            }
        });
        $rank = $variants[0];
        $query->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 WHEN LOWER(name) LIKE ? THEN 2 WHEN EXISTS (SELECT 1 FROM product_variants AS search_variants WHERE search_variants.product_id = products.id AND search_variants.is_active = 1 AND search_variants.archived_at IS NULL AND search_variants.sku LIKE ?) THEN 4 ELSE 3 END', [$rank, $rank.'%', '%'.$rank.'%', '%'.$rank.'%'])->orderByDesc('is_featured')->latest('products.created_at');

        return $query;
    }

    /** @return array<int, array<string, mixed>> */
    public function autocomplete(string $search, int $limit = 6): array
    {
        $query = $this->normalize($search);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $products = $this->applyTextSearch($this->visibleQuery(), $query)->limit(min(6, max(1, $limit)))->get();

        return $products->map(function (Product $product): array {
            $cover = $product->images->firstWhere('is_cover', true) ?? $product->images->first();
            $thumbnail = null;
            if ($cover) {
                $variant = app(ImageVariantService::class)->responsiveSources($cover->path);
                $thumbnail = $variant[400] ?? $variant[800] ?? Storage::disk('public')->url($cover->path);
            }
            $price = $product->variants->min('price_toman');
            $available = $product->variants->contains(fn ($variant): bool => $variant->available_quantity > 0);

            return ['id' => $product->id, 'name' => $product->name, 'category' => $product->category?->name, 'thumbnail' => $thumbnail, 'price_toman' => $price, 'price_label' => $price !== null ? number_format((int) $price).' تومان' : null, 'available' => $available, 'availability_label' => $available ? 'موجود' : 'ناموجود', 'url' => route('products.show', $product)];
        })->all();
    }
}
