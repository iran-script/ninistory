<?php

namespace App\Models;

use App\Services\NavigationCategoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    public const STOREFRONT_CACHE_KEY = 'storefront:categories:active:v2';

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'image_path', 'meta_title', 'meta_description', 'canonical_url', 'robots_directive', 'seo_content', 'is_active', 'sort_order', 'primary_search_intent', 'buying_guide', 'faqs', 'related_category_ids', 'editorial_review_status'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'faqs' => 'array', 'related_category_ids' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(self::STOREFRONT_CACHE_KEY);
            Cache::forget(NavigationCategoryService::CACHE_KEY);
            Cache::forget(HomepageCategorySlot::CACHE_KEY);
            Cache::forget('seo:sitemap:categories');
        });
        static::deleted(function (): void {
            Cache::forget(self::STOREFRONT_CACHE_KEY);
            Cache::forget(NavigationCategoryService::CACHE_KEY);
            Cache::forget(HomepageCategorySlot::CACHE_KEY);
            Cache::forget('seo:sitemap:categories');
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function sizeGuides(): HasMany
    {
        return $this->hasMany(SizeGuide::class);
    }

    public function homepageCategorySlots(): HasMany
    {
        return $this->hasMany(HomepageCategorySlot::class);
    }

    public function descendants(): Collection
    {
        $all = self::query()->select(['id', 'parent_id'])->get()->groupBy('parent_id');
        $ids = collect();
        $queue = collect([$this->id]);
        while ($queue->isNotEmpty()) {
            $parentId = $queue->shift();
            foreach ($all->get($parentId, collect()) as $child) {
                $ids->push((int) $child->id);
                $queue->push($child->id);
            }
        }

        return $ids;
    }
}
