<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'db' => $this->safe(fn () => DB::connection()->getPdo() !== null),
            'redis' => $this->safe(fn () => Redis::connection()->ping() !== false),
        ];

        $ok = ! in_array(false, $checks, true);

        return response()->json([
            'ok' => $ok,
            'checks' => $checks,
            'app' => config('app.name'),
        ], $ok ? 200 : 503);
    }

    private function safe(callable $fn): bool
    {
        try { return (bool) $fn(); } catch (Throwable) { return false; }
    }
}
