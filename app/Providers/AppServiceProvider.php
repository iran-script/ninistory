<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\MockPaymentGateway;
use App\Services\PaymentGatewayManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
    }
}
