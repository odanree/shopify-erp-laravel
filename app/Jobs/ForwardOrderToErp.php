<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Erp\ErpClientContract;
use App\Services\Shopify\ShopifyAdminClientContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push a Shopify order into the ERP, then tag the order in Shopify on success.
 *
 * Retry policy = exponential backoff (30s → 60s → 120s → 300s → 1h).
 * After the last attempt, the job lands in Laravel's failed_jobs table (our DLQ).
 * Idempotency: unique key = shopify webhook id, so Shopify retries do not double-post.
 */
class ForwardOrderToErp implements ShouldQueue, ShouldBeUnique
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

    public function uniqueId(): string
    {
        return $this->webhookId !== ''
            ? $this->webhookId
            : (string) ($this->payload['id'] ?? 'unknown');
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(ErpClientContract $erp, ShopifyAdminClientContract $shopify): void
    {
        $shopifyOrderId = (string) ($this->payload['id'] ?? '');

        $already = DB::table('synced_orders')->where('shopify_order_id', $shopifyOrderId)->exists();
        if ($already) {
            Log::info('order.sync.skip.already_synced', ['shopify_order_id' => $shopifyOrderId]);
            return;
        }

        $erpOrderId = $erp->createOrder($this->payload);

        DB::table('synced_orders')->insert([
            'shopify_order_id' => $shopifyOrderId,
            'erp_order_id' => $erpOrderId,
            'webhook_id' => $this->webhookId,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shopify->addOrderTag($shopifyOrderId, 'erp-synced');

        Log::info('order.sync.ok', [
            'shopify_order_id' => $shopifyOrderId,
            'erp_order_id' => $erpOrderId,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('order.sync.dead', [
            'shopify_order_id' => $this->payload['id'] ?? null,
            'webhook_id' => $this->webhookId,
            'error' => $e->getMessage(),
        ]);
    }
}
