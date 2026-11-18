<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomepageCategorySlot;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class KidokiDemoCatalogSeeder extends Seeder
{
    public const CATEGORY_PREFIX = 'demo-kidoki-';

    public const PRODUCT_BRAND = 'کیدوکی | نمونه نمایشی';

    public const IMAGE_PATH = 'demo-catalog/kidoki-demo-placeholder.svg';

    /** @return array{categories:int, products:int, variants:int, homepage_slots:int} */
    public function plan(): array
    {
        $categories = $this->categories();
        $products = $this->products();

        return [
            'categories' => count($categories),
            'products' => count($products),
            'variants' => count($products) * 9,
            'homepage_slots' => 4,
        ];
    }

    /** @return array{categories:int, products:int, variants:int, homepage_slots:int} */
    public function seedCatalog(): array
    {
        if (app()->environment('production') && ! config('demo_catalog.public_preview')) {
            throw new RuntimeException('Enable DEMO_CATALOG_PUBLIC_PREVIEW before importing the production preview catalog.');
        }

        $this->writePlaceholder();

        return DB::transaction(function (): array {
            $categoryModels = $this->seedCategories();
            $this->seedProducts($categoryModels);
            $homepageSlots = $this->seedHomepageSlots($categoryModels);

            return [
                'categories' => count($categoryModels),
                'products' => count($this->products()),
                'variants' => ProductVariant::query()->where('sku', 'like', 'DEMO-KIDOKI-%')->count(),
                'homepage_slots' => $homepageSlots,
            ];
        });
    }

    /** @return array{deleted_products:int, preserved_products:int, deleted_categories:int, removed_slots:int} */
    public function rollbackCatalog(): array
    {
        $deletedProducts = 0;
        $preservedProducts = 0;

        DB::transaction(function () use (&$deletedProducts, &$preservedProducts): void {
            Product::query()->where('slug', 'like', self::CATEGORY_PREFIX.'product-%')
                ->where('brand', self::PRODUCT_BRAND)
                ->orderBy('id')
                ->get()
                ->each(function (Product $product) use (&$deletedProducts, &$preservedProducts): void {
                    $hasHistory = $product->variants()
                        ->where(function ($variants): void {
                            $variants->whereHas('orderItems')
                                ->orWhereHas('reservations')
                                ->orWhereHas('inventoryMovements');
                        })
                        ->exists();

                    if ($hasHistory) {
                        $product->update(['is_active' => false, 'is_featured' => false]);
                        $product->variants()->update(['is_active' => false]);
                        $preservedProducts++;

                        return;
                    }

                    $product->delete();
                    $deletedProducts++;
                });
        });

        $removedSlots = HomepageCategorySlot::query()
            ->whereHas('category', fn ($categories) => $categories->where('slug', 'like', self::CATEGORY_PREFIX.'%')->where('description', 'like', '[DEMO-KIDOKI]%'))
            ->delete();
        $deletedCategories = 0;
        Category::query()->where('slug', 'like', self::CATEGORY_PREFIX.'%')->where('description', 'like', '[DEMO-KIDOKI]%')->orderByDesc('parent_id')->get()
            ->each(function (Category $category) use (&$deletedCategories): void {
                if (! $category->products()->exists() && ! $category->children()->exists()) {
                    $category->delete();
                    $deletedCategories++;
                }
            });

        if (! ProductImage::query()->where('path', self::IMAGE_PATH)->exists()
            && ! Category::query()->where('image_path', self::IMAGE_PATH)->exists()) {
            Storage::disk('public')->delete(self::IMAGE_PATH);
        }

        return [
            'deleted_products' => $deletedProducts,
            'preserved_products' => $preservedProducts,
            'deleted_categories' => $deletedCategories,
            'removed_slots' => $removedSlots,
        ];
    }

    /** @return array<string, Category> */
    private function seedCategories(): array
    {
        $models = [];
        foreach ($this->categories() as $definition) {
            $slug = self::CATEGORY_PREFIX.$definition['key'];
            $existing = Category::query()->where('slug', $slug)->first();
            if ($existing && ! str_contains((string) $existing->description, '[DEMO-KIDOKI]')) {
                throw new RuntimeException("Refusing to overwrite an untagged category slug: {$slug}");
            }
            $parent = isset($definition['parent']) ? ($models[$definition['parent']] ?? null) : null;
            $models[$definition['key']] = Category::updateOrCreate(['slug' => $slug], [
                'parent_id' => $parent?->id,
                'name' => $definition['name'],
                'description' => '[DEMO-KIDOKI] '.$definition['description'].' این دسته‌بندی برای آزمایش ویترین است و موجودی واقعی را نشان نمی‌دهد.',
                'image_path' => self::IMAGE_PATH,
                'meta_title' => $definition['name'].' | دسته‌بندی نمایشی کیدوکی',
                'meta_description' => 'دسته‌بندی نمایشی برای آزمون فروشگاه کیدوکی؛ کالاها نمونه هستند و موجودی قابل خرید ندارند.',
                'robots_directive' => 'noindex,nofollow',
                'editorial_review_status' => 'draft',
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
            ]);
        }

        return $models;
    }

    /** @param array<string, Category> $categories */
    private function seedProducts(array $categories): void
    {
        $inventory = app(InventoryService::class);
        $colors = [
            ['نام' => 'صورتی پاستلی', 'hex' => '#F2AFC1'],
            ['نام' => 'آبی آسمانی', 'hex' => '#83BCE0'],
            ['نام' => 'سبز نعنایی', 'hex' => '#9CCEB6'],
        ];
        $sizes = [
            'girl' => ['40', '45', '50'],
            'boy' => ['45', '50', '55'],
            'baby' => ['۰ تا ۳ ماه', '۳ تا ۶ ماه', '۶ تا ۹ ماه'],
            'unisex' => ['45', '50', '55'],
        ];
        $sizeCharts = [
            'girl' => ['note' => 'سایزهای نمایشی ۴۰، ۴۵ و ۵۰؛ پیش از تولید، اندازه‌گیری واقعی لازم است.'],
            'boy' => ['note' => 'سایزهای نمایشی ۴۵، ۵۰ و ۵۵؛ پیش از تولید، اندازه‌گیری واقعی لازم است.'],
            'baby' => ['note' => 'رده‌های سنی فقط نمونه رابط هستند و اندازه‌گیری واقعی لازم است.'],
            'unisex' => ['note' => 'سایزهای نمایشی ۴۵، ۵۰ و ۵۵؛ پیش از تولید، اندازه‌گیری واقعی لازم است.'],
        ];

        foreach ($this->products() as $index => $definition) {
            $number = $index + 1;
            $slug = self::CATEGORY_PREFIX.'product-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $sku = 'DEMO-KIDOKI-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $existing = Product::query()->where('slug', $slug)->first();
            if ($existing && ($existing->brand !== self::PRODUCT_BRAND || $existing->sku !== $sku)) {
                throw new RuntimeException("Refusing to overwrite a product outside the demo namespace: {$slug}");
            }
            $skuOwner = Product::query()->where('sku', $sku)->first();
            if ($skuOwner && $skuOwner->slug !== $slug) {
                throw new RuntimeException("Refusing to reuse a SKU owned by another product: {$sku}");
            }

            $product = Product::updateOrCreate(['slug' => $slug], [
                'category_id' => $categories[$definition['category']]->id,
                'sku' => $sku,
                'name' => 'نمونه نمایشی: '.$definition['name'],
                'short_description' => 'محصول نمونه برای آزمایش ظاهر و عملکرد فروشگاه؛ کالای واقعی نیست و موجودی آن صفر است.',
                'description' => 'این رکورد فقط برای آزمایش رابط فروشگاه کیدوکی ساخته شده است. تصویر، مشخصات و قیمت نمایشی‌اند؛ این محصول واقعی نیست و قابل خرید نیست. '.$definition['description'],
                'brand' => self::PRODUCT_BRAND,
                'material' => $definition['material'],
                'composition' => 'ترکیب پارچه در این نمونه صرفاً نمایشی است و باید برای کالای واقعی تأیید شود.',
                'fit_information' => 'فرم لباس در این محتوای نمایشی برای آزمایش صفحه محصول نوشته شده است.',
                'season' => $definition['season'],
                'care_instructions' => 'راهنمای شست‌وشوی واقعی برای این نمونه تأیید نشده است.',
                'age_guidance' => $definition['age'],
                'size_chart' => $sizeCharts[$definition['group']],
                'meta_title' => 'نمونه نمایشی '.$definition['name'].' | کیدوکی',
                'meta_description' => 'محصول نمونه و غیرقابل‌خرید برای آزمایش فروشگاه کیدوکی.',
                'robots_directive' => 'noindex,nofollow',
                'content_review_status' => 'draft',
                'merchant_verification_status' => 'pending',
                'is_active' => true,
                'is_featured' => $number <= 4,
            ]);

            foreach ($colors as $colorIndex => $color) {
                foreach ($sizes[$definition['group']] as $sizeIndex => $size) {
                    $variantSku = $sku.'-'.($colorIndex + 1).'-'.($sizeIndex + 1);
                    $price = 850000 + ($number * 35000);
                    $variantOwner = ProductVariant::query()->where('sku', $variantSku)->first();
                    if ($variantOwner && (int) $variantOwner->product_id !== (int) $product->id) {
                        throw new RuntimeException("Refusing to reuse a variant SKU owned by another product: {$variantSku}");
                    }
                    $variant = ProductVariant::updateOrCreate(['sku' => $variantSku], [
                        'product_id' => $product->id,
                        'size' => $size,
                        'color_name' => $color['نام'],
                        'color_hex' => $color['hex'],
                        'price_toman' => $price,
                        'compare_at_price_toman' => null,
                        'weight_grams' => $definition['group'] === 'baby' ? 180 : 300,
                        'is_active' => true,
                        'archived_at' => null,
                    ]);
                    if ((int) $variant->stock_quantity !== 0 || (int) $variant->reserved_quantity !== 0) {
                        if ((int) $variant->reserved_quantity !== 0) {
                            throw new RuntimeException("Demo variant has reserved stock and needs manual review: {$variantSku}");
                        }
                        $inventory->setPhysicalStock($variant, 0, 'DEMO-KIDOKI zero-stock preview import');
                    }
                }
            }

            ProductImage::query()->firstOrCreate([
                'product_id' => $product->id,
                'path' => self::IMAGE_PATH,
            ], [
                'alt_text' => 'تصویر نمایشی؛ کالای واقعی کیدوکی نیست',
                'caption' => 'نمونه گرافیکی برای آزمایش فروشگاه',
                'width' => 800,
                'height' => 1000,
                'sort_order' => 0,
                'is_cover' => true,
            ]);
        }
    }

    /** @param array<string, Category> $categories */
    private function seedHomepageSlots(array $categories): int
    {
        $positions = HomepageCategorySlot::query()->pluck('position')->map(fn ($position): int => (int) $position)->all();
        $usedCategories = HomepageCategorySlot::query()->whereNotNull('category_id')->pluck('category_id')->map(fn ($id): int => (int) $id)->all();
        $candidates = ['girls', 'boys', 'baby', 'sport', 'seasons', 'accessories', 'special'];
        $added = 0;
        foreach (range(1, 4) as $position) {
            if (in_array($position, $positions, true)) {
                continue;
            }
            $candidate = collect($candidates)->first(fn (string $key): bool => ! in_array((int) $categories[$key]->id, $usedCategories, true));
            if (! $candidate) {
                break;
            }
            HomepageCategorySlot::query()->create([
                'position' => $position,
                'category_id' => $categories[$candidate]->id,
                'title' => $categories[$candidate]->name,
                'image_path' => self::IMAGE_PATH,
            ]);
            $usedCategories[] = (int) $categories[$candidate]->id;
            $added++;
        }

        return $added;
    }

    private function writePlaceholder(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1000" viewBox="0 0 800 1000" role="img" aria-labelledby="title desc">
  <title id="title">تصویر نمایشی کیدوکی</title>
  <desc id="desc">تصویر گرافیکی برای نمونه محصول؛ این کالا واقعی نیست</desc>
  <rect width="800" height="1000" rx="48" fill="#f8eef2"/>
  <circle cx="400" cy="390" r="250" fill="#f4dfe7"/>
  <path d="M300 240 220 300 270 410 320 380 320 650 480 650 480 380 530 410 580 300 500 240 450 270 400 250 350 270Z" fill="#83bce0" stroke="#315d76" stroke-width="16" stroke-linejoin="round"/>
  <path d="M350 270 Q400 350 450 270" fill="none" stroke="#315d76" stroke-width="14"/>
  <rect x="185" y="760" width="430" height="100" rx="50" fill="#ffffff"/>
  <text x="400" y="825" text-anchor="middle" font-family="sans-serif" font-size="42" font-weight="700" fill="#334155">تصویر نمایشی</text>
  <text x="400" y="910" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#64748b">کالا واقعی نیست · موجودی صفر</text>
</svg>
SVG;

        if (! Storage::disk('public')->exists(self::IMAGE_PATH)
            && ! Storage::disk('public')->put(self::IMAGE_PATH, $svg)) {
            throw new RuntimeException('Unable to write the Kidoki demo placeholder to persistent public storage.');
        }
    }

    /** @return array<int, array{key:string,name:string,description:string,sort_order:int,parent?:string}> */
    private function categories(): array
    {
        $groups = [
            ['girls', 'لباس دخترانه', 10, 'پوشاک روزمره و مهمانی دخترانه', [
                ['girls-sets', 'ست دخترانه'], ['girls-top-pants', 'بلوز و شلوار دخترانه'], ['girls-dresses', 'پیراهن دخترانه'], ['girls-sarafan', 'سارافون دخترانه'], ['girls-blouse-shirt', 'شومیز دخترانه'], ['girls-tshirt', 'تیشرت دخترانه'], ['girls-blouse', 'بلوز دخترانه'], ['girls-pants', 'شلوار دخترانه'], ['girls-leggings', 'لگ دخترانه'], ['girls-skirt', 'دامن دخترانه'], ['girls-hoodie', 'هودی دخترانه'], ['girls-sweatshirt', 'سویشرت دخترانه'], ['girls-cardigan', 'ژاکت دخترانه'], ['girls-jacket', 'کاپشن دخترانه'], ['girls-coat', 'پالتو دخترانه'], ['girls-party', 'لباس مجلسی دخترانه'], ['girls-loungewear', 'لباس راحتی دخترانه'],
            ]],
            ['boys', 'لباس پسرانه', 20, 'پوشاک روزمره و رسمی پسرانه', [
                ['boys-sets', 'ست پسرانه'], ['boys-top-pants', 'بلوز و شلوار پسرانه'], ['boys-tshirt', 'تیشرت پسرانه'], ['boys-polo', 'پولوشرت پسرانه'], ['boys-shirt', 'پیراهن پسرانه'], ['boys-blouse', 'بلوز پسرانه'], ['boys-pants', 'شلوار پسرانه'], ['boys-jeans', 'شلوار جین پسرانه'], ['boys-joggers', 'شلوار اسلش پسرانه'], ['boys-shorts', 'شلوارک پسرانه'], ['boys-hoodie', 'هودی پسرانه'], ['boys-sweatshirt', 'سویشرت پسرانه'], ['boys-cardigan', 'ژاکت پسرانه'], ['boys-jacket', 'کاپشن پسرانه'], ['boys-vest', 'جلیقه پسرانه'], ['boys-loungewear', 'لباس راحتی پسرانه'], ['boys-formal', 'لباس رسمی پسرانه'],
            ]],
            ['baby', 'لباس نوزادی', 30, 'پوشاک نرم و نمایشی نوزاد', [
                ['baby-girl-sets', 'ست نوزادی دخترانه'], ['baby-boy-sets', 'ست نوزادی پسرانه'], ['baby-bodysuits', 'بادی نوزادی'], ['baby-overalls', 'سرهمی نوزادی'], ['baby-rompers', 'رامپر نوزادی'], ['baby-sleepwear', 'لباس خواب نوزادی'], ['baby-pants', 'شلوار نوزادی'], ['baby-tops', 'بلوز نوزادی'], ['baby-jackets', 'کاپشن نوزادی'], ['baby-hospital', 'لباس بیمارستانی نوزاد'], ['baby-gift', 'ست هدیه نوزادی'],
            ]],
            ['sport', 'لباس اسپرت و راحتی', 40, 'مدل‌های نمایشی برای بازی و خانه', [
                ['sport-sets', 'ست اسپرت بچگانه'], ['sport-fleece', 'ست دورس بچگانه'], ['sport-hoodie-pants', 'ست هودی و شلوار'], ['sport-tshirt-shorts', 'ست تیشرت و شلوارک'], ['sport-home', 'لباس خانگی بچگانه'], ['sport-activewear', 'لباس ورزشی بچگانه'], ['sport-siblings', 'ست خواهر برادری'],
            ]],
            ['seasons', 'لباس فصل‌ها', 50, 'دسته‌های نمایشی بر اساس فصل', [
                ['season-summer', 'لباس تابستانی بچگانه'], ['season-spring', 'لباس بهاری بچگانه'], ['season-autumn', 'لباس پاییزی بچگانه'], ['season-winter', 'لباس زمستانی بچگانه'], ['season-warm', 'لباس گرم بچگانه'], ['season-cool', 'لباس خنک بچگانه'],
            ]],
            ['accessories', 'اکسسوری بچگانه', 60, 'اکسسوری‌های نمایشی کودک', [
                ['accessory-hats', 'کلاه بچگانه'], ['accessory-scarves', 'شال گردن بچگانه'], ['accessory-gloves', 'دستکش بچگانه'], ['accessory-socks', 'جوراب بچگانه'], ['accessory-tights', 'جوراب شلواری بچگانه'], ['accessory-hair', 'تل و گیره مو'], ['accessory-bags', 'کیف بچگانه'], ['accessory-belts', 'کمربند بچگانه'],
            ]],
            ['special', 'کالکشن‌های ویژه', 70, 'مناسبت‌ها و مجموعه‌های نمایشی', [
                ['special-yalda', 'کالکشن یلدا'], ['special-nowruz', 'کالکشن نوروز'], ['special-eid', 'لباس عید بچگانه'], ['special-siblings', 'ست خواهر و برادر'], ['special-party', 'لباس مهمانی'],
            ]],
        ];

        $definitions = [];
        foreach ($groups as [$key, $name, $order, $description, $children]) {
            $definitions[] = ['key' => $key, 'name' => $name, 'description' => $description, 'sort_order' => $order];
            foreach ($children as $childOrder => [$childKey, $childName]) {
                $definitions[] = ['key' => $childKey, 'name' => $childName, 'description' => $childName.'؛ زیرمجموعه نمایشی برای مرور دسته‌بندی و فیلترها.', 'sort_order' => $order + $childOrder + 1, 'parent' => $key];
            }
        }

        return $definitions;
    }

    /** @return array<int, array{category:string,name:string,description:string,material:string,season:string,age:string,group:string}> */
    private function products(): array
    {
        $definitions = [];
        $groups = [
            ['group' => 'girl', 'age' => '۳ تا ۱۰ سال', 'products' => [
                ['ست دخترانه گلدوزی گل', 'girls-sets', 'پنبه‌ای سبک', 'بهار'], ['بلوز و شلوار دخترانه یاسی', 'girls-top-pants', 'پنبه و دورس سبک', 'چهارفصل'], ['پیراهن گل‌گلی نخی', 'girls-dresses', 'نخی لطیف', 'بهار و تابستان'], ['سارافون کتان لنین', 'girls-sarafan', 'کتان نمایشی', 'بهار و تابستان'], ['پیراهن دخترانه کمربنددار', 'girls-dresses', 'نخی بافت‌دار', 'چهارفصل'], ['ست اسپرت دخترانه آبی آسمانی', 'sport-sets', 'دورس پنبه‌ای', 'چهارفصل'], ['هودی دخترانه صورتی', 'girls-hoodie', 'دورس پنبه‌ای', 'پاییز و زمستان'], ['کاپشن دخترانه پاندا', 'girls-jacket', 'پارچه رویه نمایشی', 'زمستان'], ['شومیز دخترانه یقه چین‌دار', 'girls-blouse-shirt', 'نخی لطیف', 'بهار'], ['لگ کبریتی دخترانه', 'girls-leggings', 'پنبه کشی', 'چهارفصل'], ['دامن چین‌دار دخترانه', 'girls-skirt', 'کتان سبک', 'بهار و تابستان'], ['سویشرت دخترانه قلبی', 'girls-sweatshirt', 'دورس دو نخ', 'پاییز'], ['ژاکت بافت دخترانه', 'girls-cardigan', 'بافت نمایشی', 'پاییز'], ['لباس راحتی دخترانه ابر', 'girls-loungewear', 'پنبه‌ای نرم', 'چهارفصل'], ['پیراهن مجلسی دخترانه قصه', 'girls-party', 'پارچه مجلسی نمایشی', 'مهمانی'],
            ]],
            ['group' => 'boy', 'age' => '۳ تا ۱۰ سال', 'products' => [
                ['ست پسرانه آنالوگ', 'boys-sets', 'پنبه و دورس سبک', 'چهارفصل'], ['ست پسرانه گلدوزی بیضی', 'boys-sets', 'پنبه‌ای سبک', 'بهار'], ['ست پسرانه اسپرت کوهستان', 'sport-sets', 'دورس پنبه‌ای', 'چهارفصل'], ['ست هودی و شلوار بگ', 'sport-hoodie-pants', 'دورس دو نخ', 'پاییز و زمستان'], ['بلوز سلانیک پسرانه', 'boys-blouse', 'سلانیک نمایشی', 'پاییز'], ['ست دورس دو نخ پسرانه', 'sport-fleece', 'دورس دو نخ', 'پاییز و زمستان'], ['تیشرت پسرانه نخی ربات', 'boys-tshirt', 'نخی سبک', 'بهار و تابستان'], ['شلوار اسلش پسرانه', 'boys-joggers', 'پنبه کشی', 'چهارفصل'], ['شلوار جین پسرانه بازیگوش', 'boys-jeans', 'دنیم نمایشی', 'چهارفصل'], ['کاپشن پسرانه کلاه‌دار', 'boys-jacket', 'پارچه رویه نمایشی', 'زمستان'], ['پولوشرت پسرانه راه‌راه', 'boys-polo', 'پنبه‌ای', 'بهار و تابستان'], ['پیراهن چهارخانه پسرانه', 'boys-shirt', 'نخی بافت‌دار', 'چهارفصل'], ['شلوارک ساحلی پسرانه', 'boys-shorts', 'نخی سبک', 'تابستان'], ['جلیقه پسرانه ماجراجو', 'boys-vest', 'پارچه رویه نمایشی', 'پاییز'], ['لباس رسمی پسرانه کاپیتان', 'boys-formal', 'پارچه رسمی نمایشی', 'مهمانی'],
            ]],
            ['group' => 'baby', 'age' => '۰ تا ۹ ماه', 'products' => [
                ['ست نوزادی پنبه‌ای ماه و ستاره', 'baby-girl-sets', 'پنبه لطیف', 'چهارفصل'], ['سرهمی نوزادی خرس کوچولو', 'baby-overalls', 'پنبه لطیف', 'چهارفصل'], ['بادی نوزادی آستین بلند ابر', 'baby-bodysuits', 'پنبه لطیف', 'پاییز'], ['رامپر نوزادی گل‌دوزی', 'baby-rompers', 'نخی لطیف', 'بهار و تابستان'], ['ست هدیه نوزاد سه‌تکه', 'baby-gift', 'پنبه لطیف', 'چهارفصل'], ['لباس بیمارستانی نوزاد فرشته', 'baby-hospital', 'پنبه لطیف', 'چهارفصل'], ['شلوار نوزادی کمرنرم', 'baby-pants', 'پنبه کشی لطیف', 'چهارفصل'],
            ]],
            ['group' => 'unisex', 'age' => '۴ تا ۱۰ سال', 'products' => [
                ['ست یلدایی بچگانه انار', 'special-yalda', 'پنبه و دورس سبک', 'زمستان'], ['کاپشن زمستانی بچگانه برف', 'season-winter', 'پارچه رویه نمایشی', 'زمستان'], ['ست اسپرت خواهر برادری', 'sport-siblings', 'دورس پنبه‌ای', 'چهارفصل'],
            ]],
        ];

        foreach ($groups as $group) {
            foreach ($group['products'] as [$name, $category, $material, $season]) {
                $definitions[] = [
                    'name' => $name,
                    'category' => $category,
                    'description' => 'طراحی نمونه با حال‌وهوای '.$season.' برای نمایش انتخاب رنگ و اندازه در رابط فروشگاه.',
                    'material' => $material,
                    'season' => $season,
                    'age' => $group['age'],
                    'group' => $group['group'],
                ];
            }
        }

        return $definitions;
    }
}
