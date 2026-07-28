<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Erp\ErpClientContract;
use App\Services\Shopify\ShopifyAdminClientContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push a Shopify inventory-level change into the ERP; if the ERP returns a
 * corrected quantity (ERP is source of truth), write it back to Shopify.
 */
class ForwardInventoryUpdateToErp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;

    public function __construct(
        public readonly array $payload,
        public readonly string $webhookId,
    ) {}

    public function backoff(): array
    {
        return [30, 60, 120, 300, 3600];
    }

    public function handle(ErpClientContract $erp, ShopifyAdminClientContract $shopify): void
    {
        $inventoryItemId = (string) ($this->payload['inventory_item_id'] ?? '');
        $locationId      = (string) ($this->payload['location_id'] ?? '');
        $available       = (int)    ($this->payload['available'] ?? 0);

        $sku = $shopify->skuForInventoryItem($inventoryItemId);

        $result = $erp->updateInventory($sku, $available);

        DB::table('inventory_corrections')->insert([
            'sku' => $sku,
            'shopify_qty' => $available,
            'erp_qty' => $result['quantity'],
            'corrected' => $result['corrected'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($result['corrected']) {
            $shopify->setInventoryLevel($inventoryItemId, $locationId, $result['quantity']);
            Log::info('inventory.sync.corrected', [
                'sku' => $sku, 'shopify' => $available, 'erp' => $result['quantity'],
            ]);
        } else {
            Log::info('inventory.sync.ok', ['sku' => $sku, 'quantity' => $available]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('inventory.sync.dead', [
            'inventory_item_id' => $this->payload['inventory_item_id'] ?? null,
            'webhook_id' => $this->webhookId,
            'error' => $e->getMessage(),
        ]);
    }
}
