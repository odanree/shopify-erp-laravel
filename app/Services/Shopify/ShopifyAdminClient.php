<?php

declare(strict_types=1);

namespace App\Services\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over Shopify's Admin GraphQL / REST APIs.
 *
 * NOTE: bodies below use REST-style paths as illustrative stubs — swap for
 * GraphQL mutations in production. The important structure is the retry
 * policy on 429 (Shopify's leaky-bucket rate limiter response) and the
 * consistent shape returned to callers.
 */
class ShopifyAdminClient implements ShopifyAdminClientContract
{
    public function addOrderTag(string $shopifyOrderId, string $tag): void
    {
        $this->http()->put("/admin/api/2024-10/orders/{$shopifyOrderId}.json", [
            'order' => ['id' => $shopifyOrderId, 'tags' => $tag],
        ])->throw();
    }

    public function skuForInventoryItem(string $inventoryItemId): string
    {
        $response = $this->http()
            ->get("/admin/api/2024-10/inventory_items/{$inventoryItemId}.json")
            ->throw();

        return (string) $response->json('inventory_item.sku')
            ?: throw new RuntimeException("inventory item {$inventoryItemId} has no sku");
    }

    public function setInventoryLevel(string $inventoryItemId, string $locationId, int $quantity): void
    {
        $this->http()->post('/admin/api/2024-10/inventory_levels/set.json', [
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available' => $quantity,
        ])->throw();
    }

    public function quantityFor(string $sku): int
    {
        // Stub. Real impl: GraphQL productVariants(query: "sku:...") { inventoryQuantity }
        return 0;
    }

    public function setBySku(string $sku, int $quantity): void
    {
        // Stub. Real impl: look up inventoryItemId + locationId by sku, call setInventoryLevel.
    }

    public function skusOnOpenOrders(): array
    {
        return [];
    }

    private function http(): PendingRequest
    {
        $domain = (string) config('shopify.store_domain');

        return Http::baseUrl("https://{$domain}")
            ->withHeaders(['X-Shopify-Access-Token' => (string) config('shopify.admin_token')])
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(
                times: 4,
                sleepMilliseconds: fn (int $attempt) => 250 * (2 ** $attempt),
                when: fn ($exception) => $exception instanceof RequestException
                    && in_array($exception->response?->status(), [429, 500, 502, 503, 504], true),
            );
    }
}
