<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductReadinessService
{
    /** @return array{required: Collection, recommended: Collection, is_demo: bool, can_publish: bool, can_launch: bool} */
    public function inspect(Product $product): array
    {
        $required = collect();
        $recommended = collect();
        $isDemo = str_starts_with((string) $product->slug, (string) config('demo_catalog.prefix', 'demo-'));

        if (! filled($product->name)) {
            $required->push('نام محصول وارد نشده است.');
        }
        if (! $product->category_id) {
            $required->push('دسته‌بندی محصول مشخص نشده است.');
        }
        if (! $product->variants->contains(fn ($variant): bool => $variant->is_active && filled($variant->sku) && (int) $variant->price_toman > 0)) {
            $required->push('حداقل یک تنوع فعال با SKU و قیمت معتبر لازم است.');
        }
        if ($product->is_active && $product->images->isEmpty()) {
            $required->push('محصول منتشرشده باید عکس واقعی داشته باشد.');
        }
        if ($isDemo) {
            $required->push('این رکورد با نشانگر دموی محلی شناسایی شده و برای انتشار واقعی تأیید نشده است.');
        }

        if ($product->images->contains(fn ($image): bool => ! filled($image->alt_text))) {
            $recommended->push('برای همه عکس‌ها متن جایگزین معتبر وارد کنید.');
        }
        if (! filled($product->description) && ! filled($product->short_description)) {
            $recommended->push('توضیح کاربردی و تأییدشده محصول را اضافه کنید.');
        }
        foreach (['composition' => 'جنس یا ترکیب پارچه', 'measured_dimensions' => 'اندازه‌های واقعی', 'meta_title' => 'عنوان SEO', 'meta_description' => 'توضیح SEO'] as $field => $label) {
            if (! filled($product->{$field})) {
                $recommended->push("{$label} هنوز تکمیل نشده است.");
            }
        }

        $launchRequired = $required->values();
        if (($product->merchant_verification_status ?? 'pending') !== 'approved' && ! $isDemo) {
            $launchRequired->push('تأیید merchant برای این محصول ثبت نشده است.');
        }

        return ['required' => $required, 'recommended' => $recommended, 'is_demo' => $isDemo, 'can_publish' => $required->isEmpty(), 'can_launch' => $launchRequired->isEmpty()];
    }

    /** @return array<string, int> */
    public function dashboardCounts(): array
    {
        $prefix = (string) config('demo_catalog.prefix', 'demo-');
        $products = Product::query();

        return [
            'real_products' => (clone $products)->where('slug', 'not like', $prefix.'%')->count(),
            'demo_products' => (clone $products)->where('slug', 'like', $prefix.'%')->count(),
            'missing_photos' => (clone $products)->whereDoesntHave('images')->count(),
            'missing_sizes' => (clone $products)->whereNull('measured_dimensions')->whereNull('size_chart')->count(),
            'missing_material' => (clone $products)->whereNull('composition')->whereNull('material')->count(),
            'seo_review' => (clone $products)->where('content_review_status', '!=', 'approved')->count(),
        ];
    }
}
