<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');
Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');
Route::post('/cart', [StorefrontController::class, 'addToCart'])->name('cart.add');
Route::patch('/cart/{variant}', [StorefrontController::class, 'updateCart'])->name('cart.update');
Route::delete('/cart/{variant}', [StorefrontController::class, 'removeFromCart'])->name('cart.remove');
Route::get('/checkout', fn () => view('store.checkout', ['items' => collect(session('cart', []))->map(fn ($qty, $id) => ['variant' => \App\Models\ProductVariant::with('product')->find($id), 'quantity' => $qty])->filter()]))->name('checkout');
Route::post('/checkout', [StorefrontController::class, 'checkout'])->name('checkout.store');
Route::get('/checkout/complete/{order:number}', fn (\App\Models\Order $order) => view('store.checkout-complete', compact('order')))->name('checkout.complete');
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
        Route::resource('products', AdminProductController::class)->except(['show']);
        Route::delete('products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');
        Route::patch('variants/{variant}/stock', [AdminProductController::class, 'stock'])->name('variants.stock');
    });
});
