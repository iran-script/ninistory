<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

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
