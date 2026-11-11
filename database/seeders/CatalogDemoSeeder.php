<?php

namespace Database\Seeders;

use App\Models\{Category, Product, ProductVariant};
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('demo_catalog.enabled')) throw new \RuntimeException('Catalog demo seeding requires local/testing and DEMO_CATALOG_ENABLED=true.');
        DB::transaction(function (): void {
            $categoryModels = [];
            foreach ($this->categories() as $definition) {
                $parent = isset($definition['parent']) ? ($categoryModels[$definition['parent']] ?? null) : null;
                $categoryModels[$definition['slug']] = Category::updateOrCreate(['slug' => $definition['slug']], ['parent_id' => $parent?->id, 'name' => $definition['name'], 'description' => $definition['description'], 'is_active' => true, 'sort_order' => $definition['sort_order']]);
            }

            $inventory = app(InventoryService::class);
            foreach ($this->products() as $definition) {
                $product = Product::updateOrCreate(['slug' => $definition['slug']], ['category_id' => $categoryModels[$definition['category']]->id, 'sku' => $definition['sku'], 'name' => $definition['name'], 'short_description' => $definition['short_description'], 'description' => $definition['description'], 'brand' => 'نی‌نی استوری', 'material' => $definition['material'], 'care_instructions' => 'با آب سرد و لباس‌های هم‌رنگ بشویید؛ از سفیدکننده استفاده نکنید.', 'age_guidance' => $definition['age_guidance'], 'size_chart' => ['note' => 'اندازه‌ها تقریبی هستند؛ پیش از خرید جدول اندازه را بررسی کنید.'], 'is_active' => true, 'is_featured' => $definition['featured']]);
                foreach ($definition['variants'] as $variantDefinition) {
                    $variant = ProductVariant::updateOrCreate(['sku' => $variantDefinition['sku']], ['product_id' => $product->id, 'size' => $variantDefinition['size'], 'color_name' => $variantDefinition['color'], 'color_hex' => $variantDefinition['hex'], 'price_toman' => $variantDefinition['price'], 'compare_at_price_toman' => $variantDefinition['compare'], 'weight_grams' => $variantDefinition['weight'], 'is_active' => true]);
                    if (! $variant->inventoryMovements()->exists()) $inventory->setPhysicalStock($variant, $variantDefinition['stock'], 'ایجاد کاتالوگ آزمایشی', null, 'demo_seed');
                }
            }
        });
        $this->command?->info('Demo catalog ready: 12 categories, 40 products, 120 variants. No product photos assigned because only promotional assets are available.');
    }

    private function categories(): array
    {
        return [
            ['slug'=>'demo-girls','name'=>'دخترانه','description'=>'لباس‌های رنگی و راحت دخترانه','sort_order'=>10], ['slug'=>'demo-girls-dresses','name'=>'پیراهن و سارافون دخترانه','description'=>'پیراهن‌های روزمره و مهمانی','parent'=>'demo-girls','sort_order'=>11], ['slug'=>'demo-girls-sets','name'=>'ست و لباس راحتی دخترانه','description'=>'ست‌های کاربردی برای بازی و خانه','parent'=>'demo-girls','sort_order'=>12], ['slug'=>'demo-girls-outerwear','name'=>'لباس گرم دخترانه','description'=>'پلیور، سویشرت و ژاکت دخترانه','parent'=>'demo-girls','sort_order'=>13],
            ['slug'=>'demo-boys','name'=>'پسرانه','description'=>'لباس‌های بادوام و راحت پسرانه','sort_order'=>20], ['slug'=>'demo-boys-sets','name'=>'ست و تی‌شرت پسرانه','description'=>'ست‌های روزمره پسرانه','parent'=>'demo-boys','sort_order'=>21], ['slug'=>'demo-boys-pants','name'=>'شلوار و جین پسرانه','description'=>'شلوارهای راحت و مقاوم','parent'=>'demo-boys','sort_order'=>22], ['slug'=>'demo-boys-outerwear','name'=>'لباس گرم پسرانه','description'=>'هودی، سویشرت و کاپشن پسرانه','parent'=>'demo-boys','sort_order'=>23],
            ['slug'=>'demo-baby','name'=>'نوزاد و خردسال','description'=>'لباس‌های لطیف برای روزهای اول زندگی','sort_order'=>30], ['slug'=>'demo-baby-rompers','name'=>'سرهمی و بادی نوزادی','description'=>'لباس‌های یک‌تکه نرم و کاربردی','parent'=>'demo-baby','sort_order'=>31], ['slug'=>'demo-baby-sets','name'=>'ست بیمارستانی و نوزادی','description'=>'ست‌های لطیف برای نوزادان','parent'=>'demo-baby','sort_order'=>32], ['slug'=>'demo-baby-sleepwear','name'=>'لباس خواب نوزادی','description'=>'لباس خواب راحت و لطیف','parent'=>'demo-baby','sort_order'=>33],
        ];
    }

    private function products(): array
    {
        $girls = ['پیراهن گلبرگ صورتی','سارافون پروانه کوچک','پیراهن مهمانی آفتاب','ست تی‌شرت و دامن رنگین‌کمان','ست راحتی پنبه‌ای یاسی','بلوز آستین‌پفی بهاری','دامن چین‌دار نیلوفر','شلوارک کتان صورتی','ژاکت بافت صورتی‌گلبهی','پلیور یقه‌گرد باران','سویشرت قلبی رویا','بارانی سبک شکوفه','تونیک نخی پریسا','ست تابستانی هندوانه','پیراهن چهارخانه قصه'];
        $boys = ['ست تی‌شرت و شلوارک کوهستان','تی‌شرت ربات کوچک','ست راحتی ماشین مسابقه','پیراهن چهارخانه کاپیتان','شلوار جین بازیگوش','شلوار کتان خاکی','شلوارک ساحلی آبی','جین مامور کوچک','هودی ابر و باد','سویشرت فضانورد','ژاکت بافت جنگل','کاپشن سبک ماجراجو','پولو نخی آریا','ست تابستانی دریا','بلوز راه‌راه ملوان'];
        $baby = ['سرهمی خرس کوچولو','بادی نخی ماه و ستاره','سرهمی خواب پنبه‌ای','ست بیمارستانی فرشته','ست سه‌تکه نی‌نی','بادی آستین‌بلند ابر','شلوار نخی نوزادی','کلاه و پاپوش لطیف','لباس خواب زیپ‌دار','سرهمی گل‌دوزی شده'];
        return array_merge($this->makeGroup($girls, 'girls', ['۲ تا ۳ سال','۴ تا ۵ سال','۶ تا ۷ سال']), $this->makeGroup($boys, 'boys', ['۲ تا ۳ سال','۴ تا ۵ سال','۶ تا ۷ سال']), $this->makeGroup($baby, 'baby', ['۰ تا ۳ ماه','۳ تا ۶ ماه','۶ تا ۱۲ ماه']));
    }

    private function makeGroup(array $names, string $group, array $sizes): array
    {
        $colors = [['صورتی پاستلی','#F3B6C7'],['آبی آسمانی','#8BC7E8'],['سبز نعنایی','#A9DCC5'],['زرد کره‌ای','#F5D77A'],['یاسی','#C9B6E4'],['سفید شیری','#F7F2E8']]; $result = [];
        foreach ($names as $index => $name) {
            $number = $index + 1; $categories = $group === 'girls' ? ['demo-girls-dresses','demo-girls-sets','demo-girls-outerwear'] : ($group === 'boys' ? ['demo-boys-sets','demo-boys-pants','demo-boys-outerwear'] : ['demo-baby-rompers','demo-baby-sets','demo-baby-sleepwear']); $variants = [];
            foreach ($sizes as $variantIndex => $size) { $color = $colors[($index + $variantIndex) % count($colors)]; $stock = $variantIndex === 0 && $index % 10 === 0 ? 0 : ($variantIndex === 1 && $index % 4 === 0 ? 3 : 8 + ($index % 3)); $price = ($group === 'baby' ? 390000 : ($group === 'girls' ? 590000 : 520000)) + ($index * 25000); $variants[] = ['sku'=>'DEMO-'.strtoupper($group).'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'-'.($variantIndex + 1), 'size'=>$size, 'color'=>$color[0], 'hex'=>$color[1], 'price'=>$price, 'compare'=>$price + 80000, 'stock'=>$stock, 'weight'=>$group === 'baby' ? 180 : 280]; }
            $result[] = ['slug'=>'demo-'.$group.'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'sku'=>'DEMO-'.strtoupper($group).'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'name'=>$name, 'category'=>$categories[$index % 3], 'short_description'=>'انتخابی نرم، راحت و مناسب استفاده روزمره کودکان.', 'description'=>$name.' با دوخت تمیز و پارچه لطیف، انتخابی دوست‌داشتنی برای بازی، مهمانی و روزهای پرماجراست.', 'material'=>$group === 'baby' ? 'پنبه لطیف' : 'پنبه و الیاف نرم', 'age_guidance'=>$group === 'baby' ? '۰ تا ۱۲ ماه' : '۲ تا ۷ سال', 'featured'=>in_array($index, [0,5,10], true), 'variants'=>$variants];
        }
        return $result;
    }
}
