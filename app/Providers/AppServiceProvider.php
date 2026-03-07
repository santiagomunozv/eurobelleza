<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Shopify\ShopifyApiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Registrar ShopifyApiClient como singleton para que todas las inyecciones
        // usen la misma instancia (importante para la renovación del token)
        $this->app->singleton(ShopifyApiClient::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
