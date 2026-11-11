<?php

namespace Database\Seeders;

use App\Models\{Category, Product};
use Illuminate\Database\Seeder;

class CatalogTestFixtureSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['name' => 'Test Girls', 'slug' => 'girls'], ['name' => 'Test Boys', 'slug' => 'boys'], ['name' => 'Test Baby', 'slug' => 'baby']] as $categoryIndex => $definition) {
            $category = Category::updateOrCreate(['slug' => $definition['slug']], ['name' => $definition['name'], 'is_active' => true, 'sort_order' => $categoryIndex]);
            $product = Product::updateOrCreate(['slug' => 'test-'.$definition['slug']], ['category_id' => $category->id, 'name' => 'Test '.$definition['name'], 'description' => 'Testing fixture only', 'is_active' => true, 'is_featured' => false]);
            $product->variants()->updateOrCreate(['sku' => 'TEST-'.strtoupper($definition['slug']).'-01'], ['size' => '4Y', 'color_name' => 'Pink', 'color_hex' => '#F3B6C7', 'price_toman' => 890000, 'stock_quantity' => 8, 'is_active' => true]);
        }
    }
}
