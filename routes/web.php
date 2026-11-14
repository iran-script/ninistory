<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PickupLocationController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SeoSettingsController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShippingCheckoutController;
use App\Http\Controllers\StorefrontController;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\ProductVariant;
use App\Models\SeoRedirect;
use App\Models\ShippingMethod;
use App\Models\ShippingProvince;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'index'])->name('products.index');
Route::get('/categories/{category:slug}', [StorefrontController::class, 'category'])->missing(function () {
    $redirect = SeoRedirect::where('source_path', request()->path())->first();
    abort_unless($redirect, 404);

    return redirect()->to($redirect->target_url, $redirect->status_code);
})->name('categories.show');
Route::get('/products/{product:slug}', [StorefrontController::class, 'show'])->missing(function () {
    $redirect = SeoRedirect::where('source_path', request()->path())->first();
    abort_unless($redirect, 404);

    return redirect()->to($redirect->target_url, $redirect->status_code);
})->name('products.show');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'index'])->name('seo.sitemap');
Route::get('/sitemap-static.xml', [SeoController::class, 'static'])->name('seo.sitemap.static');
Route::get('/sitemap-products.xml', [SeoController::class, 'products'])->name('seo.sitemap.products');
Route::get('/sitemap-categories.xml', [SeoController::class, 'categories'])->name('seo.sitemap.categories');
Route::get('/sitemap-articles.xml', [SeoController::class, 'articles'])->name('seo.sitemap.articles');
Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');
Route::post('/cart', [StorefrontController::class, 'addToCart'])->name('cart.add');
Route::patch('/cart/{variant}', [StorefrontController::class, 'updateCart'])->name('cart.update');
Route::delete('/cart/{variant}', [StorefrontController::class, 'removeFromCart'])->name('cart.remove');
Route::get('/checkout', function () {
    $key = session('checkout_key');
    if (! $key) {
        $key = (string) Str::uuid();
        session(['checkout_key' => $key]);
    }

return view('store.checkout', ['checkoutKey' => $key, 'items' => collect(session('cart', []))->map(fn ($qty, $id) => ['variant' => ProductVariant::with('product')->find($id), 'quantity' => $qty])->filter(), 'provinces' => ShippingProvince::where('is_enabled', true)->with(['cities' => fn ($q) => $q->where('is_enabled', true)->orderBy('name')])->orderBy('name')->get(), 'shippingMethods' => ShippingMethod::where('is_enabled', true)->orderBy('sort_order')->get(), 'addresses' => auth()->check() ? auth()->user()->addresses()->orderByDesc('is_default')->get() : collect(), 'pickupLocations' => PickupLocation::where('is_enabled', true)->orderBy('sort_order')->get()]);
})->name('checkout');
Route::post('/checkout', [ShippingCheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/complete/{order:number}', function (Request $request, Order $order) {
    $owned = $request->user()?->id !== null && $order->user_id === $request->user()->id;
    $guestOwned = session('completed_order_number') === $order->number;
    abort_unless($owned || $guestOwned, 404);

    return view('store.checkout-complete', compact('order'));
})->name('checkout.complete');
Route::post('/payments/orders/{order}/initiate', [PaymentController::class, 'initiate'])->middleware('throttle:8,1')->name('payments.initiate');
Route::get('/payments/{payment}/mock', [PaymentController::class, 'mockShow'])->middleware('signed')->name('payments.mock.show');
Route::post('/payments/{payment}/mock', [PaymentController::class, 'mockAction'])->middleware(['signed', 'throttle:12,1'])->name('payments.mock.action');
Route::get('/payments/{payment}/result', [PaymentController::class, 'result'])->middleware('signed')->name('payments.result');
Route::middleware('auth')->prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [OrderHistoryController::class, 'index'])->name('index');
    Route::get('/{order}', [OrderHistoryController::class, 'show'])->name('show');
});
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
});
Route::get('/order-access/{token}', [OrderHistoryController::class, 'guest'])->middleware('throttle:guest-order-access')->where('token', '[A-Fa-f0-9]{128}')->name('orders.guest');
Route::get('/_local-preview/{path}', function (string $path) {
    abort_unless(app()->environment(['local', 'testing']), 404);
    abort_unless(str_starts_with($path, 'images/'), 404);
    $root = realpath(storage_path('app/demo-preview'));
    $file = realpath(storage_path('app/demo-preview/'.$path));
    abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

    return response()->file($file, ['Cache-Control' => 'private, max-age=300']);
})->where('path', '.*')->name('preview.asset');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('categories', AdminCategoryController::class)->except(['show']);
        Route::resource('banners', AdminBannerController::class)->except(['show']);
        Route::resource('products', AdminProductController::class)->except(['show']);
        Route::resource('articles', AdminArticleController::class)->except(['show']);
        Route::get('articles/{article}/preview', [AdminArticleController::class, 'preview'])->name('articles.preview');
        Route::get('seo', [SeoSettingsController::class, 'edit'])->name('seo.edit');
        Route::put('seo', [SeoSettingsController::class, 'update'])->name('seo.update');
        Route::get('inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory/{variant}/receive', [AdminInventoryController::class, 'receive'])->name('inventory.receive');
        Route::get('inventory/{variant}/movements', [AdminInventoryController::class, 'movements'])->name('inventory.movements');
        Route::get('inventory-reservations', [AdminInventoryController::class, 'reservations'])->name('inventory.reservations');
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::get('shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
        Route::post('shipping-methods', [ShippingMethodController::class, 'store'])->name('shipping-methods.store');
        Route::put('shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'update'])->name('shipping-methods.update');
        Route::post('shipping-methods/{shippingMethod}/rates', [ShippingMethodController::class, 'storeRate'])->name('shipping-rates.store');
        Route::put('shipping-rates/{shippingRate}', [ShippingMethodController::class, 'updateRate'])->name('shipping-rates.update');
        Route::delete('shipping-rates/{shippingRate}', [ShippingMethodController::class, 'destroyRate'])->name('shipping-rates.destroy');
        Route::post('shipping-methods/{shippingMethod}/preview', [ShippingMethodController::class, 'previewRate'])->name('shipping-rates.preview');
        Route::get('pickup-locations', [PickupLocationController::class, 'index'])->name('pickup-locations.index');
        Route::post('pickup-locations', [PickupLocationController::class, 'store'])->name('pickup-locations.store');
        Route::put('pickup-locations/{pickupLocation}', [PickupLocationController::class, 'update'])->name('pickup-locations.update');
        Route::delete('pickup-locations/{pickupLocation}', [PickupLocationController::class, 'destroy'])->name('pickup-locations.destroy');
        Route::post('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/refunds/{refund}/simulate', [AdminPaymentController::class, 'simulateRefund'])->name('payments.refunds.simulate');
        Route::post('payments/{payment}/reconciliation-note', [AdminPaymentController::class, 'reconciliationNote'])->name('payments.reconciliation-note');
        Route::post('orders/{order}/process', [AdminOrderController::class, 'process'])->name('orders.process');
        Route::post('orders/{order}/pack', [AdminOrderController::class, 'pack'])->name('orders.pack');
        Route::post('orders/{order}/ship', [AdminOrderController::class, 'ship'])->name('orders.ship');
        Route::post('orders/{order}/shipment-status', [AdminOrderController::class, 'shipmentStatus'])->name('orders.shipment-status');
        Route::post('orders/{order}/deliver', [AdminOrderController::class, 'deliver'])->name('orders.deliver');
        Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/notes', [AdminOrderController::class, 'note'])->name('orders.notes');
        Route::post('orders/{order}/returns', [AdminOrderController::class, 'requestReturn'])->name('orders.returns.store');
        Route::post('orders/{order}/returns/{return}/approve', [AdminOrderController::class, 'approveReturn'])->name('orders.returns.approve');
        Route::post('orders/{order}/returns/{return}/reject', [AdminOrderController::class, 'rejectReturn'])->name('orders.returns.reject');
        Route::post('orders/{order}/returns/{return}/receive', [AdminOrderController::class, 'receiveReturn'])->name('orders.returns.receive');
        Route::post('orders/{order}/returns/{return}/inspect', [AdminOrderController::class, 'inspectReturn'])->name('orders.returns.inspect');
        Route::post('orders/{order}/returns/{return}/restock', [AdminOrderController::class, 'restockReturn'])->name('orders.returns.restock');
        Route::delete('products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');
        Route::patch('variants/{variant}/stock', [AdminProductController::class, 'stock'])->name('variants.stock');
    });
});
