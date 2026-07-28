# shopify-erp-laravel

Real-time, **bidirectional** webhook-driven sync between Shopify and a third-party ERP, on **Laravel 12 / PHP 8.2**. Companion port of [`shopify-erp-integration`](../shopify-erp-integration) (Node/Express) — same domain, different stack, same production-shape thinking.

## What this exists to demonstrate

The same integration problem lands differently on Laravel than on Express. This project shows the mapping:

| Concern | Node/Express version | Laravel 12 version |
|---|---|---|
| Trust boundary (HMAC verify) | Custom middleware, raw-body plumbing | `VerifyShopifyHmac` middleware, `hash_equals` |
| Async webhook handling | Fire-and-forget `Promise` | Queued job (`ShouldQueue`) with `uniqueId` for idempotency |
| Retry policy | Hand-rolled exponential-backoff in DLQ worker | Job `backoff()` array — `[30, 60, 120, 300, 3600]` |
| Dead-letter queue | Custom `dlq.js` + polling worker | Built-in `failed_jobs` table + `queue:retry` |
| Scheduled reconciliation | AWS EventBridge cron | `Schedule::hourly()` in `bootstrap/app.php` |
| HTTP client with 429 retry | Axios interceptor | `Http::retry()` fluent builder |
| Service injection | Module import mocks | `App\Providers\AppServiceProvider` binds `*Contract` → concrete |

## Architecture

```
Shopify ──webhook──► POST /api/webhooks/orders/create
                          │  ← HMAC verify at trust boundary
                          │  ← controller returns 204 immediately
                          ▼
                     shopify-webhooks queue (redis)
                          │
                          ▼
                     ForwardOrderToErp job
                          │  ← retry: 30s → 60s → 120s → 300s → 1h
                          │  ← idempotency key = X-Shopify-Webhook-Id
                          ▼
                     ErpClient → ERP /orders
                          │
                          ├──► DB: synced_orders
                          └──► ShopifyAdminClient.addOrderTag("erp-synced")

                     After 5 tries → failed_jobs table (DLQ)
                                     │
                                     └──► GET /api/dlq/status
                                          POST /api/dlq/retry

Hourly cron ──► ReconcileInventoryCommand
                   │  ← fan-out via queue: chunk SKUs, dispatch batches
                   ▼
              ReconcileInventoryBatch × N
                   │
                   ▼
              ErpClient.quantityFor vs ShopifyAdminClient.quantityFor
                   → correct divergences (ERP is source of truth)
```

## Patterns applied (by name)

- **HMAC verification at the trust boundary** — every webhook is verified against `SHOPIFY_WEBHOOK_SECRET` before the controller ever runs. Constant-time compare via `hash_equals`. Middleware alias `shopify.hmac` in `bootstrap/app.php`.
- **Async handoff with fast ack** — controller enqueues the job and returns `204` under Shopify's 5s budget. Real work happens on the queue worker.
- **Idempotency via webhook id** — `ForwardOrderToErp` implements `ShouldBeUnique`; the `X-Shopify-Webhook-Id` becomes the uniqueness key, so Shopify's own retries can't double-post to the ERP.
- **Exponential backoff + DLQ** — Laravel's built-in retry with a per-job `backoff()` array; exhausted jobs land in `failed_jobs`, which is our DLQ. No custom queue needed.
- **Scheduled reconciliation (safety net)** — hourly cron acts as backup for any webhook Shopify never delivered or that permanently failed. `withoutOverlapping()` + `onOneServer()` for multi-worker safety.
- **Fan-out via queue** — the reconcile command chunks SKUs and dispatches batch jobs so a slow ERP call can't wedge the scheduler.
- **Service boundary (interface + concrete)** — `ErpClientContract` and `ShopifyAdminClientContract` decouple jobs from HTTP transport, so tests bind fakes.
- **Token-bucket-friendly retries** — `Http::retry()` on 429/5xx with exponential sleep pairs cleanly with Shopify's leaky-bucket rate limiter.
- **ERP is source of truth for inventory; Shopify is source of truth for orders** — reflected in which direction each writeback flows.

## Stack

| Layer | Choice |
|---|---|
| Runtime | PHP 8.2 (pinned in `composer.json` `config.platform`) |
| Framework | Laravel 12 |
| Queue | Redis (driver: `redis`), Laravel `failed_jobs` DLQ |
| Persistence | PostgreSQL 16 |
| HTTP client | `Illuminate\Http\Client` with `retry()` |
| Scheduler | Laravel scheduler, `sh` while-loop in supervisord |
| Web server | nginx + php-fpm, one container via supervisord |
| Local orchestration | Docker Compose (app + postgres + redis + mock-erp) |
| Testing | PHPUnit 11 via `php artisan test` |

## Local development

Prerequisites: Docker Desktop. **No local PHP or Composer required.**

```bash
# 1. Bring up the stack
docker compose up --build

# 2. First-time DB init happens in the entrypoint; if you need to re-run:
docker compose exec app php artisan migrate

# 3. Health check
curl http://localhost:8080/api/health
```

### Testing

```bash
# Uses sqlite :memory: + sync queue via phpunit.xml
docker compose exec app php artisan test
```

Or standalone (no compose):

```bash
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli php artisan test
```

### Poking the webhooks manually

```bash
# Generate a valid HMAC-signed request against the local secret
BODY='{"id":42,"total_price":"19.99","currency":"USD"}'
HMAC=$(echo -n "$BODY" | openssl dgst -sha256 -hmac "dev-secret-change-me" -binary | base64)

curl -X POST http://localhost:8080/api/webhooks/orders/create \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Hmac-Sha256: $HMAC" \
  -H "X-Shopify-Webhook-Id: manual-$(date +%s)" \
  -d "$BODY"

# → 204 No Content, ForwardOrderToErp queued
```

## Project layout

```
app/
├── Console/Commands/
│   └── ReconcileInventoryCommand.php   ← hourly cron entry point
├── Http/
│   ├── Controllers/
│   │   ├── ShopifyWebhookController.php
│   │   ├── SyncController.php
│   │   ├── DlqController.php
│   │   └── HealthController.php
│   └── Middleware/
│       └── VerifyShopifyHmac.php       ← trust boundary
├── Jobs/
│   ├── ForwardOrderToErp.php
│   ├── ForwardInventoryUpdateToErp.php
│   └── ReconcileInventoryBatch.php
├── Providers/
│   └── AppServiceProvider.php          ← binds *Contract → concrete
└── Services/
    ├── Erp/         (Contract + Client)
    └── Shopify/     (Contract + Client)

bootstrap/app.php                        ← middleware alias, schedule, CSRF exempt
config/{shopify.php,erp.php}
database/migrations/                     ← synced_orders, inventory_corrections, failed_jobs
docker/                                  ← nginx, supervisord, php-fpm, entrypoint, mock-erp
routes/api.php
tests/Feature/Webhooks/                  ← HMAC + dispatch tests
```

## Notes for production

- Swap `SHOPIFY_ADMIN_TOKEN` / `SHOPIFY_WEBHOOK_SECRET` to a secrets manager. Never commit real values.
- Front the app with Caddy or an ALB; keep nginx internal.
- Consider **Laravel Horizon** for a queue dashboard — drop-in over the redis driver, gives you the `/api/dlq/status` UI for free.
- Sanctum is scaffolded but the `HasApiTokens` trait is not yet on `User`; add it if you gate the `sync` / `dlq` endpoints.
- All ops writes on the `sync` and `dlq` groups should sit behind Sanctum + a role check in prod; they're open here for demo.

## License

MIT.
