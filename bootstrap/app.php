<?php

use App\Console\Commands\ReconcileInventoryCommand;
use App\Http\Middleware\VerifyShopifyHmac;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'shopify.hmac' => VerifyShopifyHmac::class,
        ]);

        // Shopify webhook routes must skip CSRF (they are protected by HMAC instead).
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(ReconcileInventoryCommand::class)
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
