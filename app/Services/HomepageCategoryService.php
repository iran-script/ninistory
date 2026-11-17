<?php

namespace App\Services;

use App\Models\HomepageCategorySlot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HomepageCategoryService
{
    public function cachedSlots(): array
    {
        return Cache::remember(HomepageCategorySlot::CACHE_KEY, now()->addSeconds(config('performance.public_cache_ttl', 300)), function (): array {
            return HomepageCategorySlot::query()
                ->with('category')
                ->orderBy('position')
                ->get()
                ->filter(fn (HomepageCategorySlot $slot): bool => (bool) $slot->category?->is_active)
                ->map(function (HomepageCategorySlot $slot): array {
                    $category = $slot->category;
                    $imagePath = $slot->image_path && Storage::disk('public')->exists($slot->image_path)
                        ? $slot->image_path
                        : ($category->image_path && Storage::disk('public')->exists($category->image_path) ? $category->image_path : null);
                    $imageUrl = $imagePath
                        ? Storage::disk('public')->url($imagePath)
                        : ($category->slug === 'girls' || $category->slug === 'demo-girls'
                            ? asset('images/ninistory/banner-girls.webp')
                            : asset('images/ninistory/banner-collection.webp'));

                    return [
                        'position' => (int) $slot->position,
                        'category_id' => (int) $category->id,
                        'title' => $slot->title ?: $category->name,
                        'category_name' => $category->name,
                        'slug' => $category->slug,
                        'url' => route('categories.show', $category),
                        'image_path' => $imagePath,
                        'image_url' => $imageUrl,
                    ];
                })
                ->values()
                ->all();
        });
    }
}
