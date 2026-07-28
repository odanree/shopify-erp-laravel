<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcileInventoryBatch;
use App\Services\Shopify\ShopifyAdminClientContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly safety-net reconciliation.
 *
 * Fan-out via queue: pull the SKU set from open orders, chunk it, dispatch
 * a ReconcileInventoryBatch per chunk. Bounds any single job's blast radius
 * and lets a slow ERP call not wedge the scheduler.
 */
class ReconcileInventoryCommand extends Command
{
    protected $signature = 'sync:reconcile-inventory {--chunk=50}';
    protected $description = 'Reconcile Shopify vs ERP inventory for SKUs on open orders (hourly).';

    public function handle(ShopifyAdminClientContract $shopify): int
    {
        $chunkSize = (int) $this->option('chunk');
        $skus = $shopify->skusOnOpenOrders();

        Log::info('reconcile.inventory.dispatch', [
            'sku_count' => count($skus), 'chunk_size' => $chunkSize,
        ]);

        foreach (array_chunk($skus, $chunkSize) as $chunk) {
            ReconcileInventoryBatch::dispatch($chunk);
        }

        $this->info('Dispatched '.count($skus)." SKUs in chunks of {$chunkSize}.");
        return self::SUCCESS;
    }
}
