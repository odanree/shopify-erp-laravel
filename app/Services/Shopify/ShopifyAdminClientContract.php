<?php

declare(strict_types=1);

namespace App\Services\Shopify;

interface ShopifyAdminClientContract
{
    public function addOrderTag(string $shopifyOrderId, string $tag): void;

    public function skuForInventoryItem(string $inventoryItemId): string;

    public function setInventoryLevel(string $inventoryItemId, string $locationId, int $quantity): void;

    public function quantityFor(string $sku): int;

    public function setBySku(string $sku, int $quantity): void;

    /** @return list<string> SKUs on open unfulfilled orders */
    public function skusOnOpenOrders(): array;
}
