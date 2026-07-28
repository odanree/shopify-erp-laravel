<?php

return [
    'base_url' => env('ERP_BASE_URL', 'http://mock-erp:3000'),
    'api_key' => env('ERP_API_KEY', ''),
    'timeout' => (int) env('ERP_TIMEOUT_SECONDS', 10),
];
