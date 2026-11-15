<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\ContentPage;
use App\Services\MockPaymentGateway;
use App\Services\PaymentGatewayManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(MockPaymentGateway::class);
        $this->app->bind(PaymentGatewayInterface::class, fn ($app) => $app->make(PaymentGatewayManager::class)->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(12)->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
        RateLimiter::for('guest-order-access', fn (Request $request) => Limit::perMinute(20)->by('ip:'.$request->ip()));
        RateLimiter::for('search-autocomplete', fn (Request $request) => Limit::perMinute(60)->by('ip:'.$request->ip()));
        View::composer('layouts.store', function ($view): void {
            $view->with('publishedContentPages', Cache::remember('storefront:published-content-pages', 300, fn (): array => ContentPage::query()->where('status', 'published')->pluck('slug')->mapWithKeys(fn (string $slug): array => [$slug => true])->all()));
        });
    }
}
