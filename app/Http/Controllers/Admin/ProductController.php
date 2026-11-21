<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SeoRedirect;
use App\Services\ImageVariantService;
use App\Services\InventoryService;
use App\Services\ProductReadinessService;
use App\Services\ProductVariantSkuService;
use App\Services\SeoMetadataService;
use App\Services\SeoSchemaService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['category', 'images' => fn ($query) => $query->where('is_cover', true)->limit(1)])->withCount('variants')->when($request->filled('q'), fn ($q) => $q->where(fn ($query) => $query->where('name', 'like', '%'.$request->input('q').'%')->orWhere('sku', 'like', '%'.$request->input('q').'%')->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', '%'.$request->input('q').'%'))))->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))->when($request->input('status') === 'published', fn ($q) => $q->where('is_active', true))->when($request->input('status') === 'draft', fn ($q) => $q->where('is_active', false))->when($request->input('demo') === 'yes', fn ($q) => $q->where('slug', 'like', config('demo_catalog.prefix').'%'))->when($request->input('demo') === 'no', fn ($q) => $q->where('slug', 'not like', config('demo_catalog.prefix').'%'))->when($request->input('photos') === 'missing', fn ($q) => $q->whereDoesntHave('images'))->when($request->input('stock') === 'low', fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereRaw('stock_quantity > reserved_quantity AND (stock_quantity - reserved_quantity) <= ?', [config('inventory.low_stock_threshold', 5)])))->latest()->paginate(20)->withQueryString();

        if ($request->input('stock') === 'out') {
            $products = Product::with(['category', 'images' => fn ($query) => $query->where('is_cover', true)->limit(1)])->withCount('variants')->whereHas('variants', fn ($variant) => $variant->where('is_active', true)->whereRaw('stock_quantity <= reserved_quantity'))->latest()->paginate(20)->withQueryString();
        }

        return view('admin.products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product, 'categories' => Category::where('is_active', true)->orderBy('name')->get(), 'readiness' => app(ProductReadinessService::class)->inspect(new Product)]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            $this->saveVariants($request, $product, isCreating: true);
            $this->saveImages($request, $product);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'محصول ساخته شد.');
    }

    public function edit(Product $product)
    {
        $product->load(['variants', 'images']);

        return view('admin.products.form', ['product' => $product, 'categories' => Category::where('is_active', true)->orderBy('name')->get(), 'readiness' => app(ProductReadinessService::class)->inspect($product)]);
    }

    public function duplicate(Product $product)
    {
        $copy = DB::transaction(function () use ($product): Product {
            $attributes = $product->only([
                'category_id', 'short_description', 'description', 'brand', 'material', 'composition',
                'fit_information', 'season', 'selling_points', 'care_instructions', 'age_guidance',
                'size_chart', 'measured_dimensions', 'meta_title', 'meta_description', 'canonical_url',
                'robots_directive', 'seo_content', 'content_review_status',
            ]);
            $attributes['name'] = $product->name.' (کپی)';
            $attributes['slug'] = Str::limit($product->slug, 170, '').'-copy-'.Str::lower(Str::random(8));
            $attributes['is_active'] = false;
            $attributes['is_featured'] = false;

            $copy = Product::create($attributes);
            $skuService = app(ProductVariantSkuService::class);

            foreach ($product->variants as $variant) {
                $skuService->create($copy, [
                    'size' => $variant->size,
                    'color_name' => $variant->color_name,
                    'color_hex' => $variant->color_hex,
                    'price_toman' => $variant->price_toman,
                    'compare_at_price_toman' => $variant->compare_at_price_toman,
                    'stock_quantity' => 0,
                    'is_active' => $variant->is_active,
                ]);
            }

            $disk = Storage::disk('public');
            $images = app(ImageVariantService::class);
            foreach ($product->images as $image) {
                if (! $disk->exists($image->path)) {
                    continue;
                }

                $extension = pathinfo($image->path, PATHINFO_EXTENSION);
                $newPath = 'products/'.$copy->id.'/'.Str::uuid().($extension ? '.'.$extension : '');
                $disk->copy($image->path, $newPath);
                foreach (config('performance.image_variant_widths', [400, 800, 1200]) as $width) {
                    $sourceVariant = $images->variantPath($image->path, (int) $width);
                    if ($disk->exists($sourceVariant)) {
                        $disk->copy($sourceVariant, $images->variantPath($newPath, (int) $width));
                    }
                }

                $copy->images()->create($image->only(['width', 'height', 'alt_text', 'caption', 'sort_order', 'is_cover']) + ['path' => $newPath]);
            }

            return $copy;
        });

        return redirect()->route('admin.products.edit', $copy)->with('status', 'پیش‌نویس محصول با تنوع‌ها و تصاویر ساخته شد؛ موجودی تنوع‌ها صفر است.');
    }

    public function preview(Product $product, SeoMetadataService $seo, SeoSchemaService $schemas)
    {
        $product->load(['variants', 'images', 'category']);

        return view('store.product', [
            'product' => $product,
            'seo' => $seo->make($product->meta_title ?: $product->name.' | پیش‌نمایش کیدوکی', $product->meta_description ?: 'پیش‌نمایش خصوصی محصول کیدوکی', route('admin.products.preview', $product), 'noindex,nofollow'),
            'schemas' => [$schemas->product($product)],
            'isAdminPreview' => true,
        ]);
    }

    public function previewPricing(Request $request, Product $product)
    {
        Money::normalizeRequest($request, ['price_toman']);
        $data = $request->validate(['variant_ids' => ['required', 'array', 'min:1'], 'variant_ids.*' => ['integer', 'exists:product_variants,id'], 'price_toman' => ['required', 'integer', 'min:1', 'max:1000000000']]);
        $variants = $product->variants()->whereIn('id', $data['variant_ids'])->get();
        abort_unless($variants->count() === count($data['variant_ids']), 422);

        return view('admin.products.price-preview', compact('product', 'variants', 'data'));
    }

    public function applyPricing(Request $request, Product $product)
    {
        Money::normalizeRequest($request, ['price_toman']);
        $data = $request->validate(['variant_ids' => ['required', 'array', 'min:1'], 'variant_ids.*' => ['integer', 'exists:product_variants,id'], 'price_toman' => ['required', 'integer', 'min:1', 'max:1000000000']]);
        $updated = $product->variants()->whereIn('id', $data['variant_ids'])->update(['price_toman' => $data['price_toman']]);

        return redirect()->route('admin.products.edit', $product)->with('status', "قیمت {$updated} تنوع به‌روزرسانی شد.");
    }

    public function verifyMerchant(Request $request, Product $product)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected,pending'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $product->update(['merchant_verification_status' => $data['decision'], 'merchant_verified_by' => $data['decision'] === 'approved' ? $request->user()->id : null, 'merchant_verified_at' => $data['decision'] === 'approved' ? now() : null, 'merchant_review_reason' => $data['reason'] ?? null]);

        return back()->with('status', 'وضعیت بررسی merchant ذخیره شد.');
    }

    public function update(Request $request, Product $product)
    {
        $oldSlug = $product->slug;
        $data = $this->validateProduct($request, $product);
        DB::transaction(function () use ($request, $data, $product) {
            $product->update($data);
            if ($request->exists('variants') && ! $request->exists('variants_complete')) {
                throw ValidationException::withMessages(['variants_complete' => 'برای ذخیره جدول کامل تنوع‌ها، صفحه را دوباره بارگذاری و فرم را کامل ارسال کنید.']);
            }
            if ($request->exists('variants_complete')) {
                $this->saveVariants($request, $product, isCreating: false);
            }
            $this->saveImageOrdering($request, $product);
            $this->saveImages($request, $product);
        });
        if ($oldSlug !== $product->slug) {
            SeoRedirect::updateOrCreate(['source_path' => 'products/'.$oldSlug], ['target_url' => route('products.show', $product), 'status_code' => 301]);
        }

        return back()->with('status', 'تغییرات محصول ذخیره شد.');
    }

    public function destroy(Product $product)
    {
        if ($product->variants()->whereHas('orderItems')->exists()) {
            return back()->withErrors(['product' => 'محصول دارای سابقه سفارش است و حذف نمی‌شود. آن را پیش‌نویس کنید.']);
        }
        $variants = app(ImageVariantService::class);
        DB::transaction(function () use ($product, $variants) {
            foreach ($product->images as $image) {
                $variants->delete($image->path);
            } $product->delete();
        });

        return redirect()->route('admin.products.index')->with('status', 'محصول حذف شد.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $variants = app(ImageVariantService::class);
        DB::transaction(function () use ($image, $product, $variants) {
            $wasCover = $image->is_cover;
            $variants->delete($image->path);
            $image->delete();
            if ($wasCover && ($replacement = $product->images()->first())) {
                $replacement->update(['is_cover' => true]);
            }
        });

        return back()->with('status', 'تصویر حذف شد.');
    }

    public function setCover(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        DB::transaction(function () use ($product, $image): void {
            $product->images()->update(['is_cover' => false]);
            $image->update(['is_cover' => true]);
        });

        return back()->with('status', 'تصویر جلد انتخاب شد.');
    }

    public function destroyVariant(Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id === $product->id, 404);
        if ($variant->orderItems()->exists() || $variant->inventoryMovements()->exists() || $variant->reservations()->exists()) {
            $variant->update(['is_active' => false, 'archived_at' => now()]);

            return back()->with('status', 'این تنوع سابقه دارد و به‌صورت امن غیرفعال شد.');
        }
        $variant->delete();

        return back()->with('status', 'تنوع حذف شد.');
    }

    public function stock(Request $request, ProductVariant $variant)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'reason' => ['required', 'string', 'min:3', 'max:180']]);
        app(InventoryService::class)->setPhysicalStock($variant, (int) $data['quantity'], $data['reason'], $request->user());

        return back()->with('status', 'موجودی و سابقه‌ی تغییر ثبت شد.');
    }

    public function stockBatch(Request $request, Product $product)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:180'],
            'inventory' => ['required', 'array'],
            'inventory.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'inventory.*.updated_at' => ['nullable', 'date'],
        ]);

        $changes = [];
        foreach ($data['inventory'] as $variantId => $change) {
            $variant = $product->variants()->whereKey($variantId)->firstOrFail();
            if ((int) $change['quantity'] !== (int) $variant->stock_quantity) {
                $changes[(int) $variantId] = $change;
            }
        }

        $updated = app(InventoryService::class)->setPhysicalStockBatch($changes, $data['reason'], $request->user());

        return back()->with('status', "موجودی {$updated} تنوع با موفقیت ثبت شد.");
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $data = $request->validate(['category_id' => ['nullable', 'integer', 'exists:categories,id'], 'name' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'string', 'max:190', 'alpha_dash:ascii', 'unique:products,slug,'.($product?->id ?? 'NULL')], 'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,'.($product?->id ?? 'NULL')], 'short_description' => ['nullable', 'string', 'max:500'], 'description' => ['nullable', 'string', 'max:10000'], 'brand' => ['nullable', 'string', 'max:160'], 'material' => ['nullable', 'string', 'max:160'], 'composition' => ['nullable', 'string', 'max:240'], 'fit_information' => ['nullable', 'string', 'max:5000'], 'season' => ['nullable', 'string', 'max:100'], 'selling_points' => ['nullable', 'string', 'max:5000'], 'care_instructions' => ['nullable', 'string', 'max:5000'], 'age_guidance' => ['nullable', 'string', 'max:160'], 'size_chart' => ['nullable', 'string', 'max:10000'], 'measured_dimensions' => ['nullable', 'string', 'max:10000'], 'content_review_status' => ['sometimes', 'in:draft,needs_review,approved'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320'], 'canonical_url' => ['nullable', 'url:http,https', 'max:500'], 'robots_directive' => ['nullable', 'in:index,follow,noindex,follow,noindex,nofollow'], 'seo_content' => ['nullable', 'string', 'max:10000'], 'is_active' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean']]);
        if (($data['is_active'] ?? false) && ! $request->boolean('save_as_draft')) {
            $variants = $request->input('variants', []);
            $request->validate(['variants' => ['required', 'array', 'min:1']]);
            if (! collect($variants)->contains(fn ($variant) => filter_var($variant['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN))) {
                throw ValidationException::withMessages(['variants' => 'Published products require at least one active variant.']);
            }
        }
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'product-'.Str::lower(Str::random(10)));
        $data['is_active'] = $request->boolean('is_active') && ! $request->boolean('save_as_draft');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['size_chart'] = filled($data['size_chart'] ?? null) ? json_decode($data['size_chart'], true, 512, JSON_THROW_ON_ERROR) : null;
        $data['measured_dimensions'] = filled($data['measured_dimensions'] ?? null) ? json_decode($data['measured_dimensions'], true, 512, JSON_THROW_ON_ERROR) : null;
        $data['content_review_status'] = $data['content_review_status'] ?? $product?->content_review_status ?? 'draft';

        return $data;
    }

    private function saveVariants(Request $request, Product $product, bool $isCreating = false): void
    {
        $submittedVariants = $request->input('variants', []);
        if (is_array($submittedVariants)) {
            foreach ($submittedVariants as $id => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['price_toman', 'compare_at_price_toman'] as $field) {
                    try {
                        $submittedVariants[$id][$field] = Money::normalize($row[$field] ?? null);
                    } catch (\InvalidArgumentException) {
                        throw ValidationException::withMessages(["variants.{$id}.{$field}" => 'مبلغ باید فقط شامل رقم‌های معتبر باشد.']);
                    }
                }
            }
            $request->merge(['variants' => $submittedVariants]);
        }
        if (! is_array($submittedVariants)) {
            throw ValidationException::withMessages(['variants' => 'جدول تنوع‌ها معتبر نیست.']);
        }
        $rules = ['variants' => ['present', 'array'], 'variants_complete' => ['sometimes', 'accepted']];
        foreach (['sku' => ['nullable', 'string', 'max:100'], 'size' => ['required', 'string', 'max:40'], 'color_name' => ['required', 'string', 'max:80'], 'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'price_toman' => ['required', 'integer', 'min:1', 'max:1000000000'], 'compare_at_price_toman' => ['nullable', 'integer', 'min:1', 'max:1000000000'], 'is_active' => ['sometimes', 'boolean'], 'updated_at' => ['nullable', 'date']] as $field => $fieldRules) {
            $rules['variants.*.'.$field] = $fieldRules;
        }
        $request->validate($rules);
        if ($submittedVariants === [] && $product->variants()->exists()) {
            throw ValidationException::withMessages(['variants' => 'برای حفظ تنوع‌های فعلی، دست‌کم یک ردیف معتبر وارد کنید.']);
        }
        $keys = [];
        $skus = [];
        $existingVariants = $product->variants()->get(['id', 'size', 'color_name']);
        $submittedVariantIds = [];
        foreach ($submittedVariants as $submittedKey => $submittedRow) {
            if (! is_array($submittedRow)) {
                continue;
            }
            $submittedId = array_key_exists('variant_id', $submittedRow)
                ? (filled($submittedRow['variant_id']) ? (int) $submittedRow['variant_id'] : null)
                : (! $isCreating && ctype_digit((string) $submittedKey) ? (int) $submittedKey : null);
            if ($submittedId !== null) {
                $submittedVariantIds[$submittedId] = true;
            }
        }
        $normalizeCombinationPart = static fn (string $value): string => mb_strtolower(strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));
        foreach ($submittedVariants as $id => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['variants' => 'ردیف تنوع نامعتبر است.']);
            }
            $variantId = array_key_exists('variant_id', $row)
                ? (filled($row['variant_id']) ? (int) $row['variant_id'] : null)
                : (! $isCreating && ctype_digit((string) $id) ? (int) $id : null);
            if ($variantId !== null && ($isCreating || ! $product->variants()->whereKey($variantId)->exists())) {
                throw ValidationException::withMessages(["variants.{$id}.variant_id" => 'تنوع انتخاب‌شده متعلق به این محصول نیست.']);
            }
            $row = validator($row, ['variant_id' => ['nullable', 'integer'], 'sku' => ['nullable', 'string', 'max:100'], 'size' => ['required', 'string', 'max:40'], 'color_name' => ['required', 'string', 'max:80'], 'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'price_toman' => ['required', 'integer', 'min:1'], 'compare_at_price_toman' => ['nullable', 'integer', 'gt:price_toman'], 'is_active' => ['sometimes', 'boolean']])->validate();
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '' && ProductVariant::query()->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->when($variantId !== null, fn ($query) => $query->whereKeyNot($variantId))->exists()) {
                throw ValidationException::withMessages(["variants.{$id}.sku" => 'این کد کالا قبلاً برای محصول دیگری ثبت شده است.']);
            }
            if ($sku !== '' && isset($skus[$sku])) {
                throw ValidationException::withMessages(["variants.{$id}.sku" => 'کد کالا تکراری است.']);
            }
            if ($sku !== '') {
                $skus[$sku] = true;
            }
            $combinationKey = $normalizeCombinationPart($row['color_name']).'|'.$normalizeCombinationPart($row['size']);
            if (isset($keys[$combinationKey])) {
                throw ValidationException::withMessages(['variants' => 'ترکیب رنگ و اندازه تکراری است.']);
            }
            $keys[$combinationKey] = true;
            $duplicate = $existingVariants->contains(fn (ProductVariant $variant): bool => $variant->id !== $variantId && isset($submittedVariantIds[$variant->id]) && $normalizeCombinationPart($variant->size) === $normalizeCombinationPart($row['size']) && $normalizeCombinationPart($variant->color_name) === $normalizeCombinationPart($row['color_name']));
            if ($duplicate) {
                throw ValidationException::withMessages(['variants' => 'ترکیب رنگ و اندازه برای این محصول تکراری است.']);
            }
            $variant = $variantId !== null ? $product->variants()->whereKey($variantId)->first() : null;
            if ($variant) {
                if (isset($submittedVariants[$id]['updated_at']) && $variant->updated_at?->toISOString() !== Carbon::parse($submittedVariants[$id]['updated_at'])->toISOString()) {
                    throw ValidationException::withMessages(['variants' => 'اطلاعات تنوع تغییر کرده است؛ صفحه را تازه‌سازی و دوباره بررسی کنید.']);
                }
                if (blank($row['sku'] ?? null)) {
                    $row['sku'] = app(ProductVariantSkuService::class)->generate($product, $row['color_name'], $row['size']);
                }
                $variant->update(collect($row)->except(['stock_quantity', 'variant_id', 'updated_at'])->all() + ['is_active' => (bool) ($row['is_active'] ?? false)]);

                continue;
            }
            app(ProductVariantSkuService::class)->create($product, collect($row)->except(['variant_id', 'updated_at'])->all() + ['stock_quantity' => 0, 'is_active' => (bool) ($row['is_active'] ?? false)]);
        }

        $submittedIds = collect($submittedVariants)->map(function (array $row, string|int $key) use ($isCreating): ?int {
            if (array_key_exists('variant_id', $row)) {
                return filled($row['variant_id']) ? (int) $row['variant_id'] : null;
            }

            return ! $isCreating && ctype_digit((string) $key) ? (int) $key : null;
        })->filter()->all();
        if ($request->boolean('variants_complete') && ! $isCreating) {
            $omitted = $product->variants()->whereNotIn('id', $submittedIds)->lockForUpdate()->get();
            foreach ($omitted as $variant) {
                $variant->update(['is_active' => false, 'archived_at' => now()]);
            }
        }
    }

    private function saveImages(Request $request, Product $product): void
    {
        $request->validate(['images' => ['sometimes', 'array', 'max:6'], 'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'image_color' => ['sometimes', 'array'], 'image_color.*' => ['nullable', 'string', 'max:80'], 'image_variant' => ['sometimes', 'array'], 'image_variant.*' => ['nullable', 'integer']]);
        $request->validate(['image_alt' => ['sometimes', 'array'], 'image_alt.*' => ['nullable', 'string', 'max:180'], 'image_caption' => ['sometimes', 'array'], 'image_caption.*' => ['nullable', 'string', 'max:180']]);
        $variants = app(ImageVariantService::class);
        foreach ($request->input('image_variant', []) as $imageKey => $variantId) {
            if ($variantId === null || $variantId === '') {
                continue;
            }
            $variant = $product->variants()->whereKey($variantId)->first();
            if (! $variant) {
                throw ValidationException::withMessages(["image_variant.$imageKey" => 'تنوع تصویر باید متعلق به همین محصول باشد.']);
            }
            $colorName = $request->input("image_color.$imageKey");
            if ($colorName && mb_strtolower(trim($colorName)) !== mb_strtolower(trim($variant->color_name))) {
                throw ValidationException::withMessages(["image_variant.$imageKey" => 'رنگ و تنوع تصویر باید با هم سازگار باشند.']);
            }
        }
        foreach ($request->file('images', []) as $index => $file) {
            $validated = validator(['image' => $file], ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']])->validate();
            $media = $variants->store($validated['image'], 'products/'.$product->id);
            try {
                $variantId = $request->input("image_variant.$index");
                $variant = $variantId ? $product->variants()->whereKey($variantId)->firstOrFail() : null;
                $colorName = $request->input("image_color.$index");
                if ($variant && $colorName && mb_strtolower($colorName) !== mb_strtolower($variant->color_name)) {
                    throw ValidationException::withMessages(["image_variant.$index" => 'رنگ و تنوع تصویر باید با هم سازگار باشند.']);
                }
                $product->images()->create(['path' => $media['path'], 'width' => $media['width'], 'height' => $media['height'], 'alt_text' => $request->input("image_alt.$index", $product->name), 'caption' => $request->input("image_caption.$index"), 'sort_order' => $product->images()->count(), 'is_cover' => $product->images()->doesntExist(), 'product_variant_id' => $variant?->id, 'color_name' => $colorName ?: $variant?->color_name]);
            } catch (\Throwable $exception) {
                $variants->delete($media['path']);
                throw $exception;
            }
        }
    }

    private function saveImageOrdering(Request $request, Product $product): void
    {
        $order = $request->input('image_order', []);
        $alt = $request->input('image_alt', []);
        $captions = $request->input('image_caption', []);
        $colors = $request->input('image_color', []);
        $variantIds = $request->input('image_variant', []);
        $newImageIndexes = array_map('strval', array_keys($request->file('images', [])));
        $colors = array_diff_key($colors, array_flip($newImageIndexes));
        $variantIds = array_diff_key($variantIds, array_flip($newImageIndexes));
        if (! is_array($order)) {
            $order = [];
        }
        if (! is_array($alt)) {
            $alt = [];
        }
        if ($order === [] && $alt === [] && $captions === [] && $colors === [] && $variantIds === []) {
            return;
        }

        $validIds = $product->images()->pluck('id')->map(fn ($id) => (string) $id)->all();
        validator(['image_order' => $order], ['image_order' => ['array'], 'image_order.*' => ['integer', 'min:0', 'max:65535']])->validate();
        abort_unless(count(array_diff(array_keys($order), $validIds)) === 0, 422);

        foreach ($order as $id => $sortOrder) {
            $product->images()->whereKey($id)->update(['sort_order' => (int) $sortOrder]);
        }
        validator(['image_alt' => $alt], ['image_alt' => ['array'], 'image_alt.*' => ['nullable', 'string', 'max:180']])->validate();
        foreach ($alt as $id => $value) {
            $product->images()->whereKey($id)->update(['alt_text' => $value ?: $product->name]);
        }
        validator(['image_caption' => $captions], ['image_caption' => ['array'], 'image_caption.*' => ['nullable', 'string', 'max:180']])->validate();
        abort_unless(count(array_diff(array_keys($captions), $validIds)) === 0, 422);
        foreach ($captions as $id => $value) {
            $product->images()->whereKey($id)->update(['caption' => $value]);
        }
        validator(['image_color' => $colors, 'image_variant' => $variantIds], ['image_color' => ['array'], 'image_color.*' => ['nullable', 'string', 'max:80'], 'image_variant' => ['array'], 'image_variant.*' => ['nullable', 'integer']])->validate();
        if (count(array_diff(array_keys($colors + $variantIds), $validIds)) !== 0) {
            throw ValidationException::withMessages(['image_variant' => 'تصویر انتخاب‌شده متعلق به این محصول نیست.']);
        }
        foreach (array_unique(array_merge(array_keys($colors), array_keys($variantIds))) as $imageId) {
            $variantId = $variantIds[$imageId] ?? null;
            $colorName = $colors[$imageId] ?? null;
            $variant = $variantId ? $product->variants()->whereKey($variantId)->first() : null;
            if ($variantId && ! $variant) {
                throw ValidationException::withMessages(["image_variant.$imageId" => 'تنوع تصویر باید متعلق به همین محصول باشد.']);
            }
            if ($variant && $colorName && mb_strtolower($colorName) !== mb_strtolower($variant->color_name)) {
                throw ValidationException::withMessages(["image_variant.$imageId" => 'رنگ و تنوع انتخاب‌شده با هم سازگار نیستند.']);
            }
            $product->images()->whereKey($imageId)->update(['color_name' => $colorName ?: $variant?->color_name, 'product_variant_id' => $variant?->id]);
        }
    }
}
