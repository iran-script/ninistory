<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomepageCategorySlot;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_reorder_a_category_tree(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parent = Category::create(['name' => 'Girls', 'slug' => 'girls', 'is_active' => true]);
        $child = Category::create(['name' => 'Dresses', 'slug' => 'dresses', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'New collection', 'slug' => 'new-collection', 'parent_id' => $parent->id, 'sort_order' => 3, 'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $this->actingAs($admin)->post(route('admin.categories.reorder'), ['categories' => [
            ['id' => $child->id, 'parent_id' => $parent->id, 'sort_order' => 1],
            ['id' => $parent->id, 'parent_id' => null, 'sort_order' => 0],
        ]])->assertRedirect();

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame(1, $child->fresh()->sort_order);
    }

    public function test_reorder_rejects_cycles_and_guests_cannot_manage_categories(): void
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'is_active' => true]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'is_active' => true]);
        $this->assertTrue($parent->descendants()->contains($child->id));

        $this->post(route('admin.categories.reorder'), ['categories' => [['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 0]]])->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create(['is_admin' => true]))->post(route('admin.categories.reorder'), ['categories' => [['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 0]]])->assertSessionHasErrors('categories');
    }

    public function test_category_impact_and_safe_delete_protect_dependencies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Used category', 'slug' => 'used-category', 'is_active' => true]);
        Product::create(['name' => 'Used product', 'slug' => 'used-product', 'category_id' => $category->id, 'is_active' => false]);
        HomepageCategorySlot::create(['position' => 1, 'category_id' => $category->id]);

        $this->actingAs($admin)->get(route('admin.categories.impact', $category))->assertOk()->assertJsonPath('direct_products', 1)->assertJsonPath('homepage_slots', 1)->assertJsonPath('can_delete', false);
        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))->assertRedirect()->assertSessionHasErrors('category');
        $this->assertModelExists($category);
    }
}
