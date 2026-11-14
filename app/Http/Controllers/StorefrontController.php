<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryReservationService;
use App\Services\SeoMetadataService;
use App\Services\SeoSchemaService;
use App\Services\ShippingAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View
    {
        $categories = Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get();
        $activeBanners = Banner::active()->get()->groupBy('position');

        return view('store.home', [
            'seo' => app(SeoMetadataService::class)->home(),
            'schemas' => [app(SeoSchemaService::class)->organization()],
            'categories' => $categories,
            'categoryImages' => $categories->mapWithKeys(fn (Category $category) => [$category->slug => $this->categoryImageUrl($category)])->all(),
            'banners' => [
                'hero' => $this->bannerMedia($activeBanners->get('hero')?->first(), [
                    'title' => 'روزهای شیرین با لباس‌های دوست‌داشتنی',
                    'subtitle' => 'مجموعه‌ی تازه نینی‌استوری',
                    'desktop_url' => asset('images/ninistory/hero-kidswear.webp'),
                    'mobile_url' => asset('images/ninistory/hero-kidswear.webp'),
                    'cta_label' => 'دیدن مجموعه', 'cta_url' => route('products.index'),
                    'alt_text' => 'مجموعه لباس کودک نینی‌استوری',
                ]),
                'secondary' => $this->bannerMedia($activeBanners->get('secondary')?->first(), [
                    'title' => 'انتخاب‌های دوست‌داشتنی', 'subtitle' => 'برای دختر کوچولوی شما',
                    'desktop_url' => asset('images/ninistory/banner-girls.webp'),
                    'mobile_url' => asset('images/ninistory/banner-girls.webp'),
                    'cta_label' => 'مشاهده محصولات', 'cta_url' => route('products.index'),
                    'alt_text' => 'پیشنهادهای لباس دخترانه نینی‌استوری',
                ]),
                'collection' => $this->bannerMedia($activeBanners->get('collection')?->first(), [
                    'title' => 'کالکشن‌های داستانی', 'subtitle' => 'قصه‌ی یک فصل تازه',
                    'desktop_url' => asset('images/ninistory/banner-collection.webp'),
                    'mobile_url' => asset('images/ninistory/banner-collection.webp'),
                    'cta_label' => 'دیدن کالکشن', 'cta_url' => route('products.index'),
                    'alt_text' => 'کالکشن داستانی نینی‌استوری',
                ]),
            ],
            'demoMediaUrl' => asset('images/ninistory/demo-dress-pink.webp'),
            'featured' => Product::with(['variants', 'images'])->where('is_active', true)->where('is_featured', true)->whereHas('variants', fn ($q) => $q->where('is_active', true))->latest()->take(4)->get(),
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

    public function index(Request $request): View
    {
        $products = Product::with(['variants', 'images'])->where('is_active', true)->whereHas('variants', fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->filled('min_price') || $request->filled('max_price'), function ($q) use ($request) {
                $q->whereHas('variants', function ($variants) use ($request) {
                    $variants->where('is_active', true)
                        ->when($request->filled('min_price'), fn ($v) => $v->where('price_toman', '>=', max(0, (int) $request->input('min_price'))))
                        ->when($request->filled('max_price'), fn ($v) => $v->where('price_toman', '<=', max(0, (int) $request->input('max_price'))));
                });
            })
            ->when($request->filled('size'), fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('size', $request->string('size'))))
            ->when($request->filled('color'), fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('color_name', $request->string('color'))))
            ->when($request->filled('material'), fn ($q) => $q->where('material', $request->string('material')))
            ->when($request->boolean('available'), fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereRaw('stock_quantity > reserved_quantity')))
            ->when($request->input('sort') === 'price_asc', fn ($q) => $q->withMin('variants', 'price_toman')->orderBy('variants_min_price_toman'))
            ->when($request->input('sort') === 'price_desc', fn ($q) => $q->withMax('variants', 'price_toman')->orderByDesc('variants_max_price_toman'))
            ->when(! in_array($request->input('sort'), ['price_asc', 'price_desc'], true), fn ($q) => $q->latest())
            ->paginate(12)->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $seoService = app(SeoMetadataService::class);
        $hasFacets = collect(array_keys($request->query()))->reject(fn ($key) => $key === 'page')->isNotEmpty();

        return view('store.products', [
            'seo' => $seoService->make('خرید لباس کودک و نوزاد | نینی‌استوری', 'انتخاب لباس کودک، دخترانه، پسرانه و نوزادی در فروشگاه اینترنتی نینی‌استوری.', route('products.index'), $hasFacets ? 'noindex,follow' : $seoService->defaults()['robots']),
            'products' => $products,
            'categories' => $categories,
            'categoryImages' => $categories->mapWithKeys(fn (Category $category) => [$category->slug => $this->categoryImageUrl($category)])->all(),
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $request->merge(['category' => $category->id]);
        $view = $this->index($request);
        $data = $view->getData();
        $data['category'] = $category->load(['parent', 'children']);
        $data['seo'] = app(SeoMetadataService::class)->category($category, $data['products']->total() > 0);
        $data['schemas'] = [app(SeoSchemaService::class)->breadcrumb([
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => $category->name, 'url' => route('categories.show', $category)],
        ])];

        return view('store.products', $data);
    }

    public function show(Product $product): View
    {
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
            $variant = ProductVariant::with('product')->find($id);

            return $variant ? ['variant' => $variant, 'quantity' => $quantity] : null;
        })->filter();

        return view('store.cart', ['items' => $items]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'integer', 'exists:product_variants,id'], 'quantity' => ['sometimes', 'integer', 'min:1', 'max:10']]);
        $variant = ProductVariant::where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true))->findOrFail($data['variant_id']);
        $cart = session('cart', []);
        $newQuantity = ($cart[$variant->id] ?? 0) + ($data['quantity'] ?? 1);
        if ($newQuantity > $variant->available_quantity) {
            return back()->withErrors(['variant_id' => 'تعداد انتخاب‌شده بیشتر از موجودی است.']);
        }
        $cart[$variant->id] = $newQuantity;
        session(['cart' => $cart]);

        return redirect()->route('cart')->with('status', 'به سبد اضافه شد.');
    }

    public function updateCart(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        abort_unless($variant->is_active && $variant->product->is_active, 404);
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
