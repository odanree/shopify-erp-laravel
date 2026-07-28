<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * DLQ = Laravel's built-in failed_jobs table. Every push-to-ERP job that
 * exhausts its retries lands here; retry with Artisan queue:retry.
 */
class DlqController extends Controller
{
    public function status(): JsonResponse
    {
        $rows = DB::table('failed_jobs')
            ->select(['id', 'connection', 'queue', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'queue' => $r->queue,
                'failed_at' => $r->failed_at,
                'exception_summary' => strtok($r->exception, "\n"),
            ]);

        return response()->json([
            'ok' => true,
            'total' => DB::table('failed_jobs')->count(),
            'recent' => $rows,
        ]);
    }

    public function retryAll(): JsonResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return response()->json([
            'ok' => true,
            'output' => trim(Artisan::output()),
        ]);
    }
}
