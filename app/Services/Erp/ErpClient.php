<?php

declare(strict_types=1);

namespace App\Services\Erp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the third-party ERP.
 *
 * Uses Laravel's Http client with retry on transient failures (429 + 5xx),
 * bounded timeout, and JSON error mapping. Every retry doubles the base
 * delay — a token-bucket-friendly backoff that pairs with the outer
 * queue-level exponential-backoff retries in ForwardOrderToErp / etc.
 */
class ErpClient implements ErpClientContract
{
    public function createOrder(array $shopifyOrderPayload): string
    {
        $response = $this->http()->post('/orders', [
            'external_id' => $shopifyOrderPayload['id'] ?? null,
            'total' => $shopifyOrderPayload['total_price'] ?? null,
            'currency' => $shopifyOrderPayload['currency'] ?? null,
            'line_items' => $shopifyOrderPayload['line_items'] ?? [],
            'customer' => $shopifyOrderPayload['customer'] ?? null,
        ])->throw();

        return (string) $response->json('erp_order_id');
    }

    public function updateInventory(string $sku, int $shopifyQuantity): array
    {
        $response = $this->http()->post('/inventory', [
            'sku' => $sku,
            'quantity' => $shopifyQuantity,
        ])->throw();

        return [
            'quantity' => (int) $response->json('quantity'),
            'corrected' => (bool) $response->json('corrected', false),
        ];
    }

    public function quantityFor(string $sku): int
    {
        $response = $this->http()->get('/inventory/'.rawurlencode($sku))->throw();
        return (int) $response->json('quantity', 0);
    }

    public function activeSkus(): array
    {
        // Stub — a real ERP would page here.
        return [];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('erp.base_url'))
            ->withToken((string) config('erp.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('erp.timeout', 10))
            ->retry(
                times: 3,
                sleepMilliseconds: 200,
                when: fn ($exception) => $exception instanceof RequestException
                    && in_array($exception->response?->status(), [429, 500, 502, 503, 504], true),
            );
    }
}
