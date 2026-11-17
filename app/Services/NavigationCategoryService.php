<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class NavigationCategoryService
{
    public const CACHE_KEY = 'storefront:navigation-categories:v3';

    /**
     * @return array<int, array{id: int, name: string, slug: string, url: string, description: string, image_url: ?string, children: array<int, array> }>
     */
    public function cachedCategories(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(config('performance.public_cache_ttl', 300)), function (): array {
            $categories = Category::query()
                ->select(['id', 'parent_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'sort_order'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
            $byParent = $categories->groupBy(fn (Category $category): int => (int) ($category->parent_id ?? 0));

            $build = function (Category $category, array $ancestors = []) use (&$build, $byParent): array {
                $categoryId = (int) $category->id;
                if (isset($ancestors[$categoryId])) {
                    return [];
                }
                $ancestors[$categoryId] = true;

                return [
                    'id' => $categoryId,
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'url' => route('categories.show', ['category' => $category->slug]),
                    'description' => (string) ($category->description ?? ''),
                    'image_url' => $category->image_path && Storage::disk('public')->exists($category->image_path)
                        ? Storage::disk('public')->url($category->image_path)
                        : null,
                    'children' => $byParent->get($categoryId, collect())->map(fn (Category $child): array => $build($child, $ancestors))->filter()->values()->all(),
                ];
            };

            return $byParent->get(0, collect())->map(fn (Category $category): array => $build($category))->filter()->values()->all();
        });
    }
}
