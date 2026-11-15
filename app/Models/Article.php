<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Article extends Model
{
    protected $fillable = ['article_category_id', 'author_id', 'title', 'slug', 'summary', 'content', 'cover_image_path', 'meta_title', 'meta_description', 'canonical_url', 'robots_directive', 'status', 'published_at', 'editorial_status', 'target_search_intent', 'related_category_id', 'source_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget('seo:sitemap:articles'));
        static::deleted(fn (): bool => Cache::forget('seo:sitemap:articles'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function relatedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'related_category_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
