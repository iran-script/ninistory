<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomepageCategorySlot;
use App\Models\Product;
use App\Models\User;
use App\Services\NavigationCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_configure_four_unique_homepage_category_slots_with_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $categories = collect(range(1, 4))->map(fn (int $position): Category => Category::create(['name' => 'Category '.$position, 'slug' => 'category-'.$position, 'is_active' => true, 'sort_order' => $position]));

        $response = $this->actingAs($admin)->put(route('admin.homepage-categories.update'), [
            'slots' => $categories->mapWithKeys(fn (Category $category, int $index): array => [$index + 1 => ['category_id' => $category->id, 'title' => 'کالکشن '.$index, 'image' => UploadedFile::fake()->image('category-'.$index.'.jpg', 800, 600)]])->all(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('homepage_category_slots', 4);
        $this->assertDatabaseHas('homepage_category_slots', ['position' => 1, 'category_id' => $categories[0]->id, 'title' => 'کالکشن 0']);
        Storage::disk('public')->assertExists(HomepageCategorySlot::firstOrFail()->image_path);
    }

    public function test_homepage_category_slots_reject_duplicate_and_inactive_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $active = Category::create(['name' => 'Active', 'slug' => 'active-home', 'is_active' => true]);
        $inactive = Category::create(['name' => 'Inactive', 'slug' => 'inactive-home', 'is_active' => false]);

        $this->actingAs($admin)->put(route('admin.homepage-categories.update'), ['slots' => [
            1 => ['category_id' => $active->id], 2 => ['category_id' => $active->id], 3 => [], 4 => [],
        ]])->assertSessionHasErrors('slots');
        $this->assertDatabaseCount('homepage_category_slots', 0);

        $this->actingAs($admin)->put(route('admin.homepage-categories.update'), ['slots' => [
            1 => ['category_id' => $inactive->id], 2 => [], 3 => [], 4 => [],
        ]])->assertSessionHasErrors('slots');
        $this->assertDatabaseCount('homepage_category_slots', 0);

        $this->actingAs($admin)->put(route('admin.homepage-categories.update'), ['slots' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []]])->assertSessionHasErrors('slots');
    }

    public function test_homepage_renders_only_configured_categories_and_real_rtl_navigation_links(): void
    {
        $first = Category::create(['name' => 'دخترانه واقعی', 'slug' => 'real-girls', 'is_active' => true, 'sort_order' => 1]);
        $child = Category::create(['name' => 'پیراهن', 'slug' => 'real-dresses', 'parent_id' => $first->id, 'is_active' => true, 'sort_order' => 1]);
        Category::create(['name' => 'Unconfigured', 'slug' => 'unconfigured', 'is_active' => true, 'sort_order' => 2]);
        HomepageCategorySlot::create(['position' => 1, 'category_id' => $first->id, 'title' => 'دخترانه منتخب']);

        $response = $this->get(route('home'));

        $response->assertOk()->assertSee('data-home-category-card', false)->assertSee($first->slug)->assertSee($child->slug)->assertSee('data-mobile-menu', false)->assertSee('data-mega-menu', false)->assertSee('data-desktop-navigation', false)->assertSee('data-mobile-navigation', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-home-category-card'));
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    public function test_non_admin_cannot_manage_homepage_category_slots(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.homepage-categories.edit'))->assertForbidden();
    }

    public function test_homepage_category_cache_stores_plain_arrays_and_survives_miss_hit_and_clear(): void
    {
        $category = Category::create(['name' => 'Cached category', 'slug' => 'cached-category', 'is_active' => true]);
        HomepageCategorySlot::create(['position' => 1, 'category_id' => $category->id, 'title' => 'Cached title']);
        Cache::forget(HomepageCategorySlot::CACHE_KEY);

        $this->get(route('home'))->assertOk();
        $cached = Cache::get(HomepageCategorySlot::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertIsArray($cached[0]);
        $this->assertSame('Cached title', $cached[0]['title']);

        $this->get(route('home'))->assertOk();
        Cache::forget(HomepageCategorySlot::CACHE_KEY);
        $this->get(route('home'))->assertOk();
    }

    public function test_navigation_cache_has_one_plain_array_contract_for_desktop_and_mobile_navigation(): void
    {
        $root = Category::create(['name' => 'Navigation root', 'slug' => 'navigation-root', 'is_active' => true]);
        $child = Category::create(['name' => 'Navigation child', 'slug' => 'navigation-child', 'parent_id' => $root->id, 'is_active' => true]);
        $grandchild = Category::create(['name' => 'Navigation grandchild', 'slug' => 'navigation-grandchild', 'parent_id' => $child->id, 'is_active' => true]);
        Category::create(['name' => 'Hidden root', 'slug' => 'hidden-root', 'is_active' => false]);
        Cache::forget(NavigationCategoryService::CACHE_KEY);

        $this->get(route('home'))->assertOk();
        $cached = Cache::get(NavigationCategoryService::CACHE_KEY);

        $this->assertIsArray($cached);
        $this->assertSame($root->id, $cached[0]['id']);
        $this->assertArrayHasKey('image_url', $cached[0]);
        $this->assertArrayHasKey('description', $cached[0]);
        $this->assertSame(route('categories.show', ['category' => $root->slug]), $cached[0]['url']);
        $this->assertSame($child->id, $cached[0]['children'][0]['id']);
        $this->assertSame($grandchild->id, $cached[0]['children'][0]['children'][0]['id']);
        $this->assertStringNotContainsString('hidden-root', json_encode($cached, JSON_THROW_ON_ERROR));

        $response = $this->get(route('home'));
        $response->assertOk()->assertSee('Navigation root')->assertSee('navigation-child')->assertSee('data-mobile-menu', false);
    }

    public function test_navigation_supports_five_active_levels_in_cold_and_warm_cache(): void
    {
        $parentId = null;
        $categories = collect(range(1, 5))->map(function (int $level) use (&$parentId): Category {
            $category = Category::create(['name' => 'Level '.$level, 'slug' => 'level-'.$level, 'parent_id' => $parentId, 'is_active' => true, 'sort_order' => $level]);
            $parentId = $category->id;

            return $category;
        });
        Cache::forget(NavigationCategoryService::CACHE_KEY);

        $first = $this->get(route('home'))->assertOk();
        $cached = Cache::get(NavigationCategoryService::CACHE_KEY);
        $current = $cached[0];
        foreach ($categories as $index => $category) {
            $this->assertSame($category->id, $current['id']);
            $this->assertSame(route('categories.show', $category), $current['url']);
            $first->assertSee($category->slug);
            if ($index < 4) {
                $current = $current['children'][0];
            }
        }
        $this->assertIsArray(Cache::get(NavigationCategoryService::CACHE_KEY));
        $this->get(route('home'))->assertOk()->assertSee('Level 5');
    }

    public function test_parent_category_listing_includes_products_from_deep_descendants(): void
    {
        $root = Category::create(['name' => 'Root', 'slug' => 'root-listing', 'is_active' => true]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child-listing', 'parent_id' => $root->id, 'is_active' => true]);
        $product = Product::create(['name' => 'Deep descendant product', 'slug' => 'deep-descendant-product', 'category_id' => $child->id, 'is_active' => true]);
        $product->variants()->create(['sku' => 'DEEP-1', 'size' => '45', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 2, 'is_active' => true]);

        $this->get(route('categories.show', $root))->assertOk()->assertSee('Deep descendant product');
    }

    public function test_empty_navigation_hierarchy_is_rendered_without_property_access_errors(): void
    {
        Category::create(['name' => 'Inactive root', 'slug' => 'inactive-only', 'is_active' => false]);
        Cache::forget(NavigationCategoryService::CACHE_KEY);

        $response = $this->get(route('home'));

        $response->assertOk()->assertSee('data-mobile-menu', false)->assertDontSee('inactive-only');
    }

    public function test_category_changes_invalidate_the_navigation_cache(): void
    {
        $category = Category::create(['name' => 'Before update', 'slug' => 'cache-invalidation', 'is_active' => true]);
        Cache::forget(NavigationCategoryService::CACHE_KEY);

        $this->get(route('home'))->assertOk()->assertSee('Before update');
        $category->update(['name' => 'After update']);

        $this->get(route('home'))->assertOk()->assertSee('After update')->assertDontSee('Before update');
    }
}
