<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    public function home(): View
    {
        return view('store.home', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'featured' => Product::with(['variants', 'images'])->where('is_active', true)->where('is_featured', true)->latest()->take(4)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $products = Product::with(['variants', 'images'])->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->boolean('available'), fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('stock_quantity', '>', 0)))
            ->when($request->input('sort') === 'price_asc', fn ($q) => $q->withMin('variants', 'price_toman')->orderBy('variants_min_price_toman'))
            ->when($request->input('sort') === 'price_desc', fn ($q) => $q->withMax('variants', 'price_toman')->orderByDesc('variants_max_price_toman'))
            ->when(!in_array($request->input('sort'), ['price_asc','price_desc'], true), fn ($q) => $q->latest())
            ->paginate(12)->withQueryString();

        return view('store.products', ['products' => $products, 'categories' => Category::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);
        return view('store.product', ['product' => $product->load(['variants', 'images', 'category'])]);
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
        $cart = session('cart', []); $newQuantity = ($cart[$variant->id] ?? 0) + ($data['quantity'] ?? 1);
        if ($newQuantity > $variant->stock_quantity) return back()->withErrors(['variant_id' => 'تعداد انتخاب‌شده بیشتر از موجودی است.']);
        $cart[$variant->id] = $newQuantity; session(['cart' => $cart]);
        return redirect()->route('cart')->with('status', 'به سبد اضافه شد.');
    }

    public function updateCart(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        abort_unless($variant->is_active && $variant->product->is_active, 404);
        if ($data['quantity'] > $variant->stock_quantity) return back()->withErrors(['quantity' => 'تعداد انتخاب‌شده بیشتر از موجودی است.']);
        $cart = session('cart', []); $cart[$variant->id] = $data['quantity']; session(['cart' => $cart]);
        return back()->with('status', 'سبد خرید به‌روز شد.');
    }

    public function removeFromCart(ProductVariant $variant): RedirectResponse
    {
        $cart = session('cart', []); unset($cart[$variant->id]); session(['cart' => $cart]);
        return back()->with('status', 'کالا از سبد حذف شد.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipient_name' => ['required', 'string', 'max:160'], 'phone' => ['required', 'string', 'max:20'], 'address' => ['required', 'string', 'max:1000'], 'postal_code' => ['required', 'string', 'max:20']]);
        $cart = session('cart', []);
        if (!$cart) return back()->withErrors(['cart' => 'سبد خرید خالی است.']);
        $order = DB::transaction(function () use ($cart, $data) {
            $variants = ProductVariant::whereIn('id', array_keys($cart))->where('is_active', true)->lockForUpdate()->get()->keyBy('id');
            abort_if($variants->count() !== count($cart), 422, 'یکی از کالاها دیگر در دسترس نیست.');
            $total = 0;
            foreach ($cart as $id => $qty) { $v = $variants[$id]; abort_if($qty > $v->stock_quantity, 422, 'موجودی کافی نیست.'); $total += $v->price_toman * $qty; }
            $order = \App\Models\Order::create(['number' => (string) Str::ulid(), 'recipient_name' => $data['recipient_name'], 'phone' => $data['phone'], 'address' => $data['address'], 'postal_code' => $data['postal_code'], 'currency' => 'IRR', 'subtotal_toman' => $total, 'status' => 'pending_payment']);
            foreach ($cart as $id => $qty) { $v = $variants[$id]; $order->items()->create(['product_variant_id' => $v->id, 'sku' => $v->sku, 'product_name' => $v->product->name, 'size' => $v->size, 'color_name' => $v->color_name, 'unit_price_toman' => $v->price_toman, 'quantity' => $qty, 'line_total_toman' => $v->price_toman * $qty]); $v->decrement('stock_quantity', $qty); }
            return $order;
        });
        session()->forget('cart');
        return redirect()->route('checkout.complete', $order);
    }
}
