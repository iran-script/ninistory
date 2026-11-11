<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\{MockPaymentGateway, PaymentGatewayManager};
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
        //
    }
}
