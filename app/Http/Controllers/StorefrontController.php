<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DemoCatalogPurchaseGuard;
use App\Services\HomepageCategoryService;
use App\Services\InventoryReservationService;
use App\Services\ProductSearchService;
use App\Services\SeoMetadataService;
use App\Services\SeoSchemaService;
use App\Services\ShippingAddressService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;

class StorefrontController extends Controller
{
    public function home(): View
    {
        $search = app(ProductSearchService::class);
        $homepageCategorySlots = app(HomepageCategoryService::class)->cachedSlots();
        $activeBanners = collect(Cache::remember(Banner::STOREFRONT_CACHE_KEY, now()->addSeconds(config('performance.public_cache_ttl', 300)), fn (): array => Banner::active()->get()->map(fn (Banner $banner): array => $banner->getAttributes())->all()))
            ->map(fn (array $attributes): Banner => Banner::query()->getModel()->newFromBuilder($attributes))
            ->groupBy('position');
        $heroSlides = $activeBanners->get('hero', collect())
            ->map(fn (Banner $banner): array => $this->bannerMedia($banner, []))
            ->filter(fn (array $slide): bool => $slide !== [])
            ->values();
        if ($heroSlides->isEmpty()) {
            $heroSlides->push($this->bannerMedia(null, [
                'title' => 'روزهای شیرین با لباس‌های دوست‌داشتنی',
                'subtitle' => 'مجموعه‌ی تازه کیدوکی',
                'desktop_url' => asset('images/ninistory/hero-kidswear.webp'),
                'mobile_url' => asset('images/ninistory/hero-kidswear.webp'),
                'cta_label' => 'دیدن مجموعه', 'cta_url' => route('products.index'),
                'alt_text' => 'مجموعه لباس کودک کیدوکی',
                'duration_seconds' => 6, 'text_position' => 'right', 'overlay_style' => 'soft',
            ]));
        }

        return view('store.home', [
            'seo' => app(SeoMetadataService::class)->home(),
            'schemas' => [app(SeoSchemaService::class)->organization(), app(SeoSchemaService::class)->website()],
            'homepageCategorySlots' => $homepageCategorySlots,
            'heroSlides' => $heroSlides,
            'banners' => [
                'hero' => $this->bannerMedia($activeBanners->get('hero')?->first(), [
                    'title' => 'روزهای شیرین با لباس‌های دوست‌داشتنی',
                    'subtitle' => 'مجموعه‌ی تازه کیدوکی',
                    'desktop_url' => asset('images/ninistory/hero-kidswear.webp'),
                    'mobile_url' => asset('images/ninistory/hero-kidswear.webp'),
                    'cta_label' => 'دیدن مجموعه', 'cta_url' => route('products.index'),
                    'alt_text' => 'مجموعه لباس کودک کیدوکی',
                ]),
                'secondary' => $this->bannerMedia($activeBanners->get('secondary')?->first(), [
                    'title' => 'انتخاب‌های دوست‌داشتنی', 'subtitle' => 'برای دختر کوچولوی شما',
                    'desktop_url' => asset('images/ninistory/banner-girls.webp'),
                    'mobile_url' => asset('images/ninistory/banner-girls.webp'),
                    'cta_label' => 'مشاهده محصولات', 'cta_url' => route('products.index'),
                    'alt_text' => 'پیشنهادهای لباس دخترانه کیدوکی',
                ]),
                'collection' => $this->bannerMedia($activeBanners->get('collection')?->first(), [
                    'title' => 'کالکشن‌های داستانی', 'subtitle' => 'قصه‌ی یک فصل تازه',
                    'desktop_url' => asset('images/ninistory/banner-collection.webp'),
                    'mobile_url' => asset('images/ninistory/banner-collection.webp'),
                    'cta_label' => 'دیدن کالکشن', 'cta_url' => route('products.index'),
                    'alt_text' => 'کالکشن داستانی کیدوکی',
                ]),
            ],
            'demoMediaUrl' => asset('images/ninistory/demo-dress-pink.webp'),
            'featured' => $search->visibleQuery()->where('is_featured', true)->latest('products.created_at')->take(4)->get(),
            'newArrivals' => $search->visibleQuery()->latest('products.created_at')->take(4)->get(),
            'offers' => $search->visibleQuery()->whereHas('variants', fn ($variants) => $variants->where('is_active', true)->whereNull('archived_at')->whereColumn('compare_at_price_toman', '>', 'price_toman'))->latest('products.created_at')->take(4)->get(),
            'sizeShortcuts' => ProductVariant::query()->where('is_active', true)->whereNull('archived_at')->whereHas('product', fn (Builder $products): Builder => $products->where('is_active', true))->select('size')->distinct()->orderBy('size')->limit(8)->pluck('size'),
        ]);
    }

    private function bannerMedia(?Banner $banner, array $fallback): array
    {
        if (! $banner || ! Storage::disk('public')->exists($banner->desktop_image_path)) {
            return $fallback;
        }

        return [
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'desktop_url' => Storage::disk('public')->url($banner->desktop_image_path),
            'mobile_url' => $banner->mobile_image_path && Storage::disk('public')->exists($banner->mobile_image_path)
                ? Storage::disk('public')->url($banner->mobile_image_path)
                : Storage::disk('public')->url($banner->desktop_image_path),
            'cta_label' => $banner->cta_label,
            'cta_url' => $banner->cta_url ?: route('products.index'),
            'alt_text' => $banner->alt_text,
            'duration_seconds' => max(3, min(15, (int) ($banner->duration_seconds ?: 6))),
            'text_position' => $banner->text_position ?: 'right',
            'overlay_style' => $banner->overlay_style ?: 'soft',
        ];
    }

    private function categoryImageUrl(Category $category): string
    {
        if ($category->image_path && Storage::disk('public')->exists($category->image_path)) {
            return Storage::disk('public')->url($category->image_path);
        }

        return match ($category->slug) {
            'girls' => asset('images/ninistory/banner-girls.webp'),
            'boys', 'demo-boys', 'baby', 'newborn', 'demo-baby' => asset('images/ninistory/banner-collection.webp'),
            'demo-girls' => asset('images/ninistory/banner-girls.webp'),
            default => asset('images/ninistory/banner-collection.webp'),
        };
    }

    public function index(Request $request, ProductSearchService $search): View
    {
        $categoryIds = [];
        if ($request->filled('category')) {
            $category = Category::query()->find($request->integer('category'));
            if ($category) {
                $categoryIds = $category->descendants()->prepend($category->id)->map(fn (int|string $id): int => (int) $id)->all();
            }
        }

        $filters = $request->only(['size', 'color', 'min_price', 'max_price', 'available', 'material']);
        $filters['category_ids'] = $categoryIds;
        $productsQuery = $search->visibleQuery();
        if ($request->filled('q')) {
            $search->applyTextSearch($productsQuery, $request->string('q')->toString());
        }
        $search->applyFilters($productsQuery, $filters);
        $sort = $request->input('sort', 'relevant');
        if ($sort === 'price_asc') {
            $productsQuery->withMin(['variants' => fn ($variants) => $variants->where('is_active', true)->whereNull('archived_at')], 'price_toman')->orderBy('variants_min_price_toman');
        } elseif ($sort === 'price_desc') {
            $productsQuery->withMax(['variants' => fn ($variants) => $variants->where('is_active', true)->whereNull('archived_at')], 'price_toman')->orderByDesc('variants_max_price_toman');
        } elseif (! $request->filled('q')) {
            $productsQuery->orderByDesc('is_featured')->latest('products.created_at');
        }

        $products = $productsQuery
            ->paginate(12)->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $facetVariants = ProductVariant::query()->where('is_active', true)->whereNull('archived_at')->whereHas('product', function ($products) use ($search): void {
            $search->applyProductionVisibility($products->where('is_active', true));
        });
        $facetMaterials = Product::query()->whereNotNull('material')->where('material', '!=', '')->whereHas('variants', fn (Builder $variants): Builder => $variants->where('is_active', true)->whereNull('archived_at'));
        $search->applyProductionVisibility($facetMaterials);
        $seoService = app(SeoMetadataService::class);
        $hasFacets = collect(array_keys($request->query()))->reject(fn ($key) => $key === 'page')->isNotEmpty();
        $selectedSizes = array_values(array_unique(array_merge(array_filter((array) $request->input('size', [])), $search->normalizedFilterValues($request->input('size', [])))));
        $selectedColors = array_values(array_unique(array_merge(array_filter((array) $request->input('color', [])), $search->normalizedFilterValues($request->input('color', [])))));
        $activeFilterLinks = [];
        foreach (['size' => 'سایز', 'color' => 'رنگ'] as $filter => $label) {
            $selected = array_values(array_filter((array) $request->input($filter, [])));
            foreach ($selected as $active) {
                $remaining = array_values(array_diff($selected, [$active]));
                $query = $request->query();
                unset($query['page']);
                if ($remaining) {
                    $query[$filter] = $remaining;
                } else {
                    unset($query[$filter]);
                }
                $activeFilterLinks[] = ['label' => $label.' '.$active, 'url' => route('products.index', $query)];
            }
        }
        if ($request->boolean('available')) {
            $query = $request->query();
            unset($query['page'], $query['available']);
            $activeFilterLinks[] = ['label' => 'موجود', 'url' => route('products.index', $query)];
        }

        return view('store.products', [
            'seo' => $seoService->make('خرید لباس کودک و نوزاد | کیدوکی', 'انتخاب لباس کودک، دخترانه، پسرانه و نوزادی در فروشگاه اینترنتی کیدوکی.', route('products.index'), $hasFacets ? 'noindex,follow' : $seoService->defaults()['robots']),
            'products' => $products,
            'categories' => $categories,
            'facetSizes' => (clone $facetVariants)->selectRaw('TRIM(size) as size')->distinct()->orderBy('size')->pluck('size'),
            'facetColors' => (clone $facetVariants)->selectRaw('TRIM(color_name) as color_name')->distinct()->orderBy('color_name')->pluck('color_name'),
            'facetMaterials' => $facetMaterials->where('is_active', true)->distinct()->orderBy('material')->pluck('material'),
            'selectedSizes' => $selectedSizes,
            'selectedColors' => $selectedColors,
            'activeFilterLinks' => $activeFilterLinks,
            'categoryImages' => $categories->mapWithKeys(fn (Category $category) => [$category->slug => $this->categoryImageUrl($category)])->all(),
        ]);
    }

    public function autocomplete(Request $request, ProductSearchService $search): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = $search->normalize((string) ($data['q'] ?? ''));

        return response()->json([
            'query' => $query,
            'results' => $search->autocomplete($query),
            'categories' => $search->autocompleteCategories($query),
            'all_results_url' => route('products.index', ['q' => $query]),
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $request->merge(['category' => $category->id]);
        $view = $this->index($request, app(ProductSearchService::class));
        $data = $view->getData();
        $data['category'] = $category->load(['parent', 'children']);
        $data['relatedCategories'] = Category::query()->whereIn('id', $category->related_category_ids ?: [])->where('is_active', true)->get();
        $data['sizeGuide'] = $category->sizeGuides()->where('is_published', true)->latest()->first();
        $data['seo'] = app(SeoMetadataService::class)->category($category, $data['products']->total() > 0);
        $data['schemas'] = [app(SeoSchemaService::class)->breadcrumb([
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => $category->name, 'url' => route('categories.show', $category)],
        ])];

        return view('store.products', $data);
    }

    public function show(Product $product): View
    {
        abort_unless(! app(DemoCatalogPurchaseGuard::class)->isPreviewProduct($product) || config('demo_catalog.public_preview'), 404);
        abort_unless($product->is_active && $product->variants()->where('is_active', true)->exists(), 404);
        $product = $product->load(['variants', 'images', 'category']);

        return view('store.product', ['product' => $product, 'seo' => app(SeoMetadataService::class)->product($product), 'schemas' => [app(SeoSchemaService::class)->product($product), app(SeoSchemaService::class)->breadcrumb(array_filter([
            ['name' => 'خانه', 'url' => route('home')],
            $product->category ? ['name' => $product->category->name, 'url' => route('categories.show', $product->category)] : null,
            ['name' => $product->name, 'url' => route('products.show', $product)],
        ]))]]);
    }

    public function cart(): View
    {
        $items = collect(session('cart', []))->map(function ($quantity, $id) {
            $variant = ProductVariant::with(['product.images' => fn ($query) => $query->where('is_cover', true)])->find($id);

            return $variant ? ['variant' => $variant, 'quantity' => $quantity] : null;
        })->filter();

        return view('store.cart', ['items' => $items]);
    }

    public function addToCart(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'integer', 'exists:product_variants,id'], 'quantity' => ['sometimes', 'integer', 'min:1', 'max:10']]);
        $variant = ProductVariant::with(['product.images'])->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))->findOrFail($data['variant_id']);
        app(DemoCatalogPurchaseGuard::class)->ensurePurchasable($variant->product);
        $cart = session('cart', []);
        $newQuantity = ($cart[$variant->id] ?? 0) + ($data['quantity'] ?? 1);
        if ($newQuantity > $variant->available_quantity) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'تعداد انتخاب‌شده بیشتر از موجودی است.'], 422);
            }

            return back()->withErrors(['variant_id' => 'تعداد انتخاب‌شده بیشتر از موجودی است.']);
        }
        $cart[$variant->id] = $newQuantity;
        session(['cart' => $cart]);

        if ($request->expectsJson()) {
            $cover = $variant->product->images->firstWhere('is_cover', true) ?? $variant->product->images->first();
            $cartVariants = ProductVariant::query()->whereIn('id', array_keys($cart))->get()->keyBy('id');

            return response()->json([
                'message' => 'کالا با موفقیت به سبد خرید اضافه شد.',
                'cart_count' => count($cart),
                'cart_items_count' => array_sum($cart),
                'cart_total_toman' => collect($cart)->sum(fn (int $quantity, int|string $id): int => (int) ($cartVariants[$id]?->price_toman ?? 0) * $quantity),
                'item' => [
                    'name' => $variant->product->name,
                    'color' => $variant->color_name,
                    'size' => $variant->size,
                    'quantity' => $newQuantity,
                    'price_toman' => $variant->price_toman,
                    'price_label' => Money::toman($variant->price_toman),
                    'thumbnail' => $cover ? Storage::disk('public')->url($cover->path) : null,
                ],
            ]);
        }

        return redirect()->route('cart')->with('status', 'به سبد اضافه شد.');
    }

    public function updateCart(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        abort_unless($variant->is_active && $variant->product->is_active, 404);
        app(DemoCatalogPurchaseGuard::class)->ensurePurchasable($variant->product);
        if ($data['quantity'] > $variant->available_quantity) {
            return back()->withErrors(['quantity' => 'تعداد انتخاب‌شده بیشتر از موجودی است.']);
        }
        $cart = session('cart', []);
        $cart[$variant->id] = $data['quantity'];
        session(['cart' => $cart]);

        return back()->with('status', 'سبد خرید به‌روز شد.');
    }

    public function removeFromCart(ProductVariant $variant): RedirectResponse
    {
        $cart = session('cart', []);
        unset($cart[$variant->id]);
        session(['cart' => $cart]);

        return back()->with('status', 'کالا از سبد حذف شد.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipient_name' => ['required', 'string', 'max:160'], 'phone' => ['required', 'string', 'max:20'], 'address' => ['required', 'string', 'max:1000'], 'building' => ['nullable', 'string', 'max:120'], 'postal_code' => ['required', 'string', 'max:20'], 'province_code' => ['required', 'string', 'max:20'], 'city_code' => ['required', 'string', 'max:40'], 'shipping_method' => ['required', 'string', 'max:40'], 'checkout_key' => ['nullable', 'string', 'max:100']]);
        $data = app(ShippingAddressService::class)->validate($data);
        $data['checkout_key'] = ($data['checkout_key'] ?? null) ?: session('checkout_key') ?: (string) Str::uuid();
        $cart = session('cart', []);
        if (! $cart) {
            return back()->withErrors(['cart' => 'سبد خرید خالی است.']);
        }
        try {
            $order = DB::transaction(function () use ($cart, $data) {
                $existing = Order::where('checkout_key', $data['checkout_key'])->first();
                if ($existing) {
                    return $existing;
                }
                $variants = ProductVariant::with('product')->whereIn('id', array_keys($cart))->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))->lockForUpdate()->get()->keyBy('id');
                abort_if($variants->count() !== count($cart), 422, 'یکی از کالاها دیگر در دسترس نیست.');
                $total = 0;
                $weight = 0;
                foreach ($cart as $id => $qty) {
                    $v = $variants[$id];
                    abort_if($qty > $v->available_quantity, 422, 'موجودی کافی نیست.');
                    $total += $v->price_toman * $qty;
                }
                $order = Order::create(['number' => (string) Str::ulid(), 'checkout_key' => $data['checkout_key'], 'guest_access_token' => hash('sha512', Str::random(64)), 'user_id' => auth()->id(), 'recipient_name' => $data['recipient_name'], 'phone' => $data['phone'], 'address' => $data['address'], 'postal_code' => $data['postal_code'], 'currency' => 'IRR', 'subtotal_toman' => $total, 'status' => Order::PENDING_PAYMENT, 'payment_status' => Order::PAYMENT_PENDING, 'fulfillment_status' => Order::FULFILLMENT_AWAITING, 'shipment_status' => Order::SHIPMENT_NOT_SHIPPED]);
                foreach ($cart as $id => $qty) {
                    $v = $variants[$id];
                    $order->items()->create(['product_variant_id' => $v->id, 'sku' => $v->sku, 'product_name' => $v->product->name, 'size' => $v->size, 'color_name' => $v->color_name, 'unit_price_toman' => $v->price_toman, 'quantity' => $qty, 'line_total_toman' => $v->price_toman * $qty]);
                }
                app(InventoryReservationService::class)->reserveOrder($order, collect($cart)->map(fn ($qty, $id) => ['variant' => $variants[$id], 'quantity' => $qty]), $data['checkout_key']);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['cart' => $e->getMessage()])->withInput();
        }
        session(['completed_order_number' => $order->number]);
        session()->forget(['cart', 'checkout_key']);

        return redirect()->route('checkout.complete', $order);
    }
}
