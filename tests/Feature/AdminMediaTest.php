<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_banners_with_internal_cta_and_uploaded_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
            'title' => 'Hero', 'subtitle' => 'New season', 'alt_text' => 'Hero image',
            'position' => 'hero', 'sort_order' => 1, 'cta_label' => 'Shop', 'cta_url' => '/products',
            'is_active' => 1, 'desktop_image' => UploadedFile::fake()->image('hero.jpg', 1200, 600),
            'mobile_image' => UploadedFile::fake()->image('hero-mobile.jpg', 900, 900),
        ]);

        $response->assertRedirect(route('admin.banners.index'));
        $banner = Banner::firstOrFail();
        Storage::disk('public')->assertExists($banner->desktop_image_path);
        Storage::disk('public')->assertExists($banner->mobile_image_path);
        $this->get(route('admin.banners.index'))->assertOk()->assertSee('Hero');

        $this->actingAs($admin)->post(route('admin.banners.store'), [
            'title' => 'Unsafe', 'alt_text' => 'Unsafe', 'position' => 'hero', 'sort_order' => 2,
            'cta_url' => 'https://example.com', 'desktop_image' => UploadedFile::fake()->image('x.jpg', 1200, 600),
        ])->assertSessionHasErrors('cta_url');
    }

    public function test_admin_can_upload_replace_and_remove_category_cover_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true, 'sort_order' => 1]);
        $payload = ['name' => 'Girls', 'slug' => 'girls', 'sort_order' => 1, 'is_active' => 1];

        $this->actingAs($admin)->put(route('admin.categories.update', $category), $payload + [
            'image' => UploadedFile::fake()->image('girls.jpg', 800, 600),
        ])->assertRedirect();
        $category->refresh();
        $oldPath = $category->image_path;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), $payload + [
            'remove_image' => 1,
        ])->assertRedirect();
        $this->assertNull($category->fresh()->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }
}
