<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class SeoSchemaService
{
    public function product(Product $product): array
    {
        $variants = $product->variants->where('is_active', true);
        $images = $product->images->filter(fn ($image) => Storage::disk('public')->exists($image->path))->map(fn ($image) => asset('storage/'.$image->path))->values()->all();
        $data = ['@context' => 'https://schema.org', '@type' => $variants->count() > 1 ? 'ProductGroup' : 'Product', '@id' => route('products.show', $product).'#product', 'name' => $product->name, 'description' => strip_tags($product->description ?: $product->short_description ?: $product->name), 'sku' => $product->sku ?: $variants->first()?->sku, 'url' => route('products.show', $product)];
        if ($images) {
            $data['image'] = $images;
        }
        if ($product->brand) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $product->brand];
        }
        if ($variants->count() > 1) {
            $data['productGroupID'] = $product->sku ?: 'product-'.$product->id;
            $data['variesBy'] = ['https://schema.org/size', 'https://schema.org/color'];
            $data['hasVariant'] = $variants->map(fn ($variant) => ['@type' => 'Product', 'name' => $product->name.' - '.$variant->color_name.' '.$variant->size, 'sku' => $variant->sku, 'offers' => $this->offer($variant->price_toman, $variant->available_quantity > 0, $variant->sku, route('products.show', $product))])->values()->all();
        } elseif ($variants->count() === 1) {
            $variant = $variants->first();
            $data['offers'] = $this->offer($variant->price_toman, $variant->available_quantity > 0, $variant->sku, route('products.show', $product));
        }

        return $data;
    }

    public function breadcrumb(array $items): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($items)->values()->map(fn ($item, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url'] ?? null])->all()];
    }

    public function organization(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'نینی‌استوری', 'url' => url('/')];
    }

    public function article(Article $article): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article->title, 'description' => strip_tags($article->summary ?: $article->content), 'url' => route('articles.show', $article), 'datePublished' => $article->published_at?->toIso8601String(), 'dateModified' => $article->updated_at?->toIso8601String(), 'author' => ['@type' => 'Person', 'name' => $article->author?->name ?: 'نینی‌استوری']];
    }

    private function offer(int $toman, bool $available, string $sku, string $url): array
    {
        return ['@type' => 'Offer', 'url' => $url, 'priceCurrency' => 'IRR', 'price' => $this->rials($toman), 'availability' => 'https://schema.org/'.($available ? 'InStock' : 'OutOfStock'), 'itemCondition' => 'https://schema.org/NewCondition', 'sku' => $sku];
    }

    public function rials(int $toman): int
    {
        return $toman * 10;
    }
}
