<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'image_path', 'meta_title', 'meta_description', 'canonical_url', 'robots_directive', 'seo_content', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
