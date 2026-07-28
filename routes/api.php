<?php

use App\Http\Controllers\DlqController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

// Public health probe (also available at /up via Laravel's built-in health route).
Route::get('/health', HealthController::class);

// Trust-boundary group: HMAC verified before controller runs.
Route::middleware('shopify.hmac')->prefix('webhooks')->group(function () {
    Route::post('/orders/create', [ShopifyWebhookController::class, 'orderCreate']);
    Route::post('/inventory/update', [ShopifyWebhookController::class, 'inventoryUpdate']);
});

// Ops surface — protect behind Sanctum in prod. Open for local demo.
Route::prefix('sync')->group(function () {
    Route::get('/inventory', [SyncController::class, 'reconcileNow']);
    Route::get('/orders/pending', [SyncController::class, 'pendingOrders']);
});

Route::prefix('dlq')->group(function () {
    Route::get('/status', [DlqController::class, 'status']);
    Route::post('/retry', [DlqController::class, 'retryAll']);
});
