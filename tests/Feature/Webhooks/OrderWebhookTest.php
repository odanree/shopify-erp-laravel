<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\ForwardOrderToErp;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopify.webhook_secret', 'test-secret');
    }

    public function test_it_rejects_a_request_with_a_missing_hmac_header(): void
    {
        $this->postJson('/api/webhooks/orders/create', ['id' => 123])
            ->assertStatus(401);
    }

    public function test_it_rejects_a_request_with_a_tampered_body(): void
    {
        $body = json_encode(['id' => 123]);
        $hmac = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));

        // Same signature, but different body — must fail.
        $this->call(
            method: 'POST',
            uri: '/api/webhooks/orders/create',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            ],
            content: json_encode(['id' => 999]),
        )->assertStatus(401);
    }

    public function test_it_accepts_a_valid_signature_and_dispatches_the_job(): void
    {
        Queue::fake();

        $body = json_encode(['id' => 123, 'total_price' => '99.00', 'currency' => 'USD']);
        $hmac = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));

        $this->call(
            method: 'POST',
            uri: '/api/webhooks/orders/create',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
                'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'wh_test_1',
            ],
            content: $body,
        )->assertStatus(204);

        Queue::assertPushedOn('shopify-webhooks', ForwardOrderToErp::class);
    }
}
