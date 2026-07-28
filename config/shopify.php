<?php

return [
    // Rotate via SHOPIFY_WEBHOOK_SECRET. HMAC verification uses this secret
    // to authenticate every webhook at the trust boundary before dispatch.
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', ''),

    'store_domain' => env('SHOPIFY_STORE_DOMAIN', ''),

    'admin_token' => env('SHOPIFY_ADMIN_TOKEN', ''),

    'api_version' => env('SHOPIFY_API_VERSION', '2024-10'),
];
