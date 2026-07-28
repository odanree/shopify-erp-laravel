<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('shopify.webhook_secret');
        $header = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        if ($secret === '' || $header === '') {
            abort(401, 'missing hmac');
        }

        $computed = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($computed, $header)) {
            abort(401, 'invalid hmac');
        }

        return $next($request);
    }
}
