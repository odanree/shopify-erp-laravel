<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ForwardInventoryUpdateToErp;
use App\Jobs\ForwardOrderToErp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function orderCreate(Request $request): Response
    {
        $payload = $request->json()->all();
        $shopifyOrderId = (string) ($payload['id'] ?? '');
        $webhookId = (string) $request->header('X-Shopify-Webhook-Id', '');

        Log::info('shopify.webhook.orders_create.received', [
            'shopify_order_id' => $shopifyOrderId,
            'webhook_id' => $webhookId,
        ]);

        ForwardOrderToErp::dispatch($payload, $webhookId)
            ->onQueue('shopify-webhooks');

        return response()->noContent();
    }

    public function inventoryUpdate(Request $request): Response
    {
        $payload = $request->json()->all();
        $inventoryItemId = (string) ($payload['inventory_item_id'] ?? '');
        $webhookId = (string) $request->header('X-Shopify-Webhook-Id', '');

        Log::info('shopify.webhook.inventory_update.received', [
            'inventory_item_id' => $inventoryItemId,
            'webhook_id' => $webhookId,
        ]);

        ForwardInventoryUpdateToErp::dispatch($payload, $webhookId)
            ->onQueue('shopify-webhooks');

        return response()->noContent();
    }
}
