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
use Illuminate\Support\Facades\Log;

/**
 * One chunk of the hourly reconciliation fan-out.
 *
 * Compares Shopify vs ERP for each SKU in the batch and pushes ERP truth
 * back to Shopify when they diverge. Acts as the safety net for any
 * inventory-update webhook that was dropped or delayed.
 */
class ReconcileInventoryBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public readonly array $skus) {}

    public function handle(ErpClientContract $erp, ShopifyAdminClientContract $shopify): void
    {
        foreach ($this->skus as $sku) {
            $shopifyQty = $shopify->quantityFor($sku);
            $erpQty     = $erp->quantityFor($sku);

            if ($shopifyQty === $erpQty) {
                continue;
            }

            $shopify->setBySku($sku, $erpQty);

            Log::info('reconcile.inventory.corrected', [
                'sku' => $sku, 'shopify' => $shopifyQty, 'erp' => $erpQty,
            ]);
        }
    }
}
