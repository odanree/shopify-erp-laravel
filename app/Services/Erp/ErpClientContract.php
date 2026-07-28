<?php

declare(strict_types=1);

namespace App\Services\Erp;

interface ErpClientContract
{
    /** @return string ERP-side order id */
    public function createOrder(array $shopifyOrderPayload): string;

    /**
     * @return array{quantity: int, corrected: bool}
     */
    public function updateInventory(string $sku, int $shopifyQuantity): array;

    public function quantityFor(string $sku): int;

    /** @return list<string> active SKUs the ERP knows about */
    public function activeSkus(): array;
}
