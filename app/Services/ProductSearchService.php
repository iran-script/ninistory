<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        $tokens = array_values(array_filter(preg_split('/\s+/u', $variants[0]) ?: []));
        foreach ($tokens as $token) {
            $tokenVariants = $this->searchVariants($token);
            $query->where(function (Builder $products) use ($tokenVariants): void {
                foreach ($tokenVariants as $tokenVariant) {
                    $like = '%'.$tokenVariant.'%';
                    $products->orWhere('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('short_description', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('material', 'like', $like)
                        ->orWhereHas('variants', fn (Builder $variant): Builder => $variant->where('is_active', true)->whereNull('archived_at')->where(function (Builder $match) use ($like): void {
                            $match->where('sku', 'like', $like)->orWhere('color_name', 'like', $like)->orWhere('size', 'like', $like);
                        }))
                        ->orWhereHas('category', fn (Builder $category): Builder => $category->where('name', 'like', $like));
                }
            });
        }
        $rank = $variants[0];
        $query->orderByRaw('CASE WHEN LOWER(name) = ? OR LOWER(sku) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 WHEN LOWER(sku) LIKE ? THEN 2 WHEN EXISTS (SELECT 1 FROM product_variants AS search_variants WHERE search_variants.product_id = products.id AND search_variants.is_active = ? AND search_variants.archived_at IS NULL AND (search_variants.sku LIKE ? OR search_variants.color_name LIKE ? OR search_variants.size LIKE ?)) THEN 3 ELSE 4 END', [$rank, $rank, $rank.'%', $rank.'%', true, '%'.$rank.'%', '%'.$rank.'%', '%'.$rank.'%'])->orderByDesc('is_featured')->latest('products.created_at');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $sizes = $this->filterValues($filters['size'] ?? []);
        $colors = $this->filterValues($filters['color'] ?? []);
        $minPrice = $this->normalizePriceFilter($filters['min_price'] ?? null);
        $maxPrice = $this->normalizePriceFilter($filters['max_price'] ?? null);

        if ($sizes !== [] || $colors !== [] || $minPrice !== null || $maxPrice !== null || ! empty($filters['available'])) {
            $query->whereHas('variants', function (Builder $variants) use ($sizes, $colors, $minPrice, $maxPrice, $filters): void {
                $variants->where('is_active', true)->whereNull('archived_at')
                    ->when($sizes !== [], fn (Builder $variant) => $variant->whereIn(DB::raw($this->normalizedColumnExpression('size')), $sizes))
                    ->when($colors !== [], fn (Builder $variant) => $variant->whereIn(DB::raw($this->normalizedColumnExpression('color_name')), $colors))
                    ->when($minPrice !== null, fn (Builder $variant) => $variant->where('price_toman', '>=', $minPrice))
                    ->when($maxPrice !== null, fn (Builder $variant) => $variant->where('price_toman', '<=', $maxPrice))
                    ->when(! empty($filters['available']), fn (Builder $variant) => $variant->whereRaw('stock_quantity > reserved_quantity'));
            });
        }

        if (filled($filters['material'] ?? null)) {
            $query->where('material', $filters['material']);
        }

        if (filled($filters['category_ids'] ?? null)) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        return $query;
    }

    private function normalizePriceFilter(mixed $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Money::normalize($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<int, string> */
    public function normalizedFilterValues(mixed $value): array
    {
        $values = $this->filterValues($value);

        if ($values === []) {
            return [];
        }

        return $this->filterSearchVariants($values);
    }

    /** @return array<int, string> */
    private function filterValues(mixed $value): array
    {
        return array_values(array_filter(array_map(fn (mixed $item): string => $this->normalize((string) $item), is_array($value) ? $value : [$value])));
    }

    /** @param array<int, string> $values */
    private function filterSearchVariants(array $values): array
    {
        $asciiDigits = '0123456789';
        $persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        $arabicDigits = '٠١٢٣٤٥٦٧٨٩';

        return array_values(array_unique(array_merge(...array_map(function (string $value) use ($asciiDigits, $persianDigits, $arabicDigits): array {
            return array_merge($this->searchVariants($value), [strtr($value, array_combine(str_split($asciiDigits), preg_split('//u', $persianDigits, -1, PREG_SPLIT_NO_EMPTY))), strtr($value, array_combine(str_split($asciiDigits), preg_split('//u', $arabicDigits, -1, PREG_SPLIT_NO_EMPTY)))]);
        }, $values))));
    }

    private function normalizedColumnExpression(string $column): string
    {
        $expression = 'LOWER(TRIM('.$column.'))';
        $digits = [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];

        $characters = ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', '‌' => ' '];
        foreach ($characters as $from => $to) {
            $expression = "REPLACE({$expression}, '{$from}', '{$to}')";
        }

        foreach ($digits as $from => $to) {
            $expression = "REPLACE({$expression}, '{$from}', '{$to}')";
        }

        return $expression;
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

            return ['id' => $product->id, 'name' => $product->name, 'category' => $product->category?->name, 'thumbnail' => $thumbnail, 'price_toman' => $price, 'price_label' => $price !== null ? Money::toman($price) : null, 'available' => $available, 'availability_label' => $available ? 'موجود' : 'ناموجود', 'url' => route('products.show', $product)];
        })->all();
    }

    /** @return array<int, array{id:int,name:string,url:string}> */
    public function autocompleteCategories(string $search, int $limit = 4): array
    {
        $normalized = $this->normalize($search);
        if (mb_strlen($normalized) < 2) {
            return [];
        }

        return Category::query()->where('is_active', true)->where('name', 'like', '%'.$normalized.'%')->orderBy('sort_order')->limit($limit)->get(['id', 'name', 'slug'])->map(fn (Category $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'url' => route('categories.show', $category),
        ])->all();
    }
}
