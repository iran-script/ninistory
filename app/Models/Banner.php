<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Banner extends Model
{
    public const POSITIONS = [
        'hero' => 'بنر اصلی صفحه خانه',
        'secondary' => 'بنر تبلیغاتی دوم',
        'collection' => 'معرفی کالکشن',
    ];

    protected $fillable = [
        'title', 'subtitle', 'desktop_image_path', 'mobile_image_path', 'alt_text',
        'cta_label', 'cta_url', 'position', 'sort_order', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function scopeForPosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position)->orderBy('sort_order')->orderByDesc('id');
    }
}
