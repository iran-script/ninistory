<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'sku', 'short_description', 'description', 'brand', 'material', 'composition', 'fit_information', 'season', 'selling_points', 'care_instructions', 'size_chart', 'measured_dimensions', 'age_guidance', 'meta_title', 'meta_description', 'canonical_url', 'robots_directive', 'seo_content', 'is_active', 'is_featured', 'content_review_status', 'content_verified_at', 'merchant_verification_status', 'merchant_verified_by', 'merchant_verified_at', 'merchant_review_reason'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean', 'size_chart' => 'array', 'measured_dimensions' => 'array', 'content_verified_at' => 'datetime', 'merchant_verified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('seo:sitemap:products');
            Cache::forget('seo:sitemap:categories');
        });
        static::deleted(function (): void {
            Cache::forget('seo:sitemap:products');
            Cache::forget('seo:sitemap:categories');
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
}
