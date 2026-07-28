<?php

namespace App\Providers;

use App\Services\Erp\ErpClient;
use App\Services\Erp\ErpClientContract;
use App\Services\Shopify\ShopifyAdminClient;
use App\Services\Shopify\ShopifyAdminClientContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service boundary: bind interfaces to concretions so jobs can be
        // exercised in tests with fake implementations.
        $this->app->singleton(ErpClientContract::class, ErpClient::class);
        $this->app->singleton(ShopifyAdminClientContract::class, ShopifyAdminClient::class);
    }

    public function boot(): void
    {
        //
    }
}
