<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['نوزاد','baby'], ['دخترانه','girls'], ['پسرانه','boys']] as $i => [$name,$slug]) {
            $category = Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true, 'sort_order' => $i]);
            foreach ([['سرهمی پنبه‌ای قصه','cotton-romper'], ['ست روزهای آفتابی','sunny-days-set']] as $j => [$title,$productSlug]) {
                $product = Product::updateOrCreate(['slug' => $productSlug.'-'.$slug], ['category_id' => $category->id, 'name' => $title, 'description' => 'لباس نرم و راحت برای ماجراجویی‌های کوچک.', 'care_instructions' => 'شست‌وشو با آب سرد و رنگ‌های مشابه', 'is_active' => true, 'is_featured' => $j === 0]);
                $product->variants()->updateOrCreate(['sku' => strtoupper($slug).'-'.($j + 1).'-01'], ['size' => '۶ تا ۱۲ ماه', 'color_name' => 'صورتی', 'color_hex' => '#F6B8C8', 'price_toman' => 890000, 'stock_quantity' => 8, 'is_active' => true]);
            }
        }
    }
}
