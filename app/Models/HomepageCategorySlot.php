<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class HomepageCategorySlot extends Model
{
    public const CACHE_KEY = 'storefront:homepage-category-slots:v2';

    protected $fillable = ['position', 'category_id', 'title', 'image_path'];

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
