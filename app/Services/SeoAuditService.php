<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

class SeoAuditService
{
    public function findings(): Collection
    {
        $findings = collect();

        Category::query()->where('is_active', true)->withCount(['products' => fn ($query) => $query->where('is_active', true)])->get()->each(function (Category $category) use ($findings): void {
            if ($category->products_count === 0) {
                $findings->push(['type' => 'empty_category', 'severity' => 'warning', 'subject' => $category->name, 'message' => 'Indexable category has no active products.']);
            }
            if (! filled($category->description) && ! filled($category->seo_content)) {
                $findings->push(['type' => 'missing_category_content', 'severity' => 'suggestion', 'subject' => $category->name, 'message' => 'Add a category introduction or buying guide.']);
            }
        });

        Category::query()->where('is_active', true)->whereNull('parent_id')->get()->each(function (Category $category) use ($findings): void {
            if ($category->products()->where('is_active', true)->doesntExist() && $category->children()->doesntExist()) {
                $findings->push(['type' => 'orphan_commercial_page', 'severity' => 'warning', 'subject' => $category->name, 'message' => 'Commercial category has no products or child navigation.']);
            }
        });

        Product::query()->where('is_active', true)->withCount(['images', 'variants'])->get()->each(function (Product $product) use ($findings): void {
            if (! filled($product->description) && ! filled($product->short_description)) {
                $findings->push(['type' => 'missing_product_description', 'severity' => 'warning', 'subject' => $product->name, 'message' => 'Add verified product description.']);
            }
            if ($product->images_count === 0) {
                $findings->push(['type' => 'missing_product_photo', 'severity' => 'warning', 'subject' => $product->name, 'message' => 'Add a real product photograph before publishing.']);
            }
            if ($product->variants_count === 0) {
                $findings->push(['type' => 'missing_product_variants', 'severity' => 'warning', 'subject' => $product->name, 'message' => 'Add verified size/color variants.']);
            }
            if (! $product->category_id) {
                $findings->push(['type' => 'orphan_commercial_page', 'severity' => 'warning', 'subject' => $product->name, 'message' => 'Published product has no category link.']);
            }
        });

        $titleGroups = Product::query()->whereNotNull('meta_title')->get()->groupBy('meta_title');
        $titleGroups->filter(fn (Collection $products): bool => $products->count() > 1)->each(function (Collection $products, string $title) use ($findings): void {
            $findings->push(['type' => 'duplicate_meta_title', 'severity' => 'warning', 'subject' => $title, 'message' => $products->pluck('name')->implode('، ')]);
        });

        Article::query()->where('status', 'published')->whereNull('related_category_id')->get()->each(function (Article $article) use ($findings): void {
            $findings->push(['type' => 'article_without_category_link', 'severity' => 'suggestion', 'subject' => $article->title, 'message' => 'Link the article to a relevant category before publishing.']);
        });

        return $findings->values();
    }
}
