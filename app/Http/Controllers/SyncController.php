<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Console\Commands\ReconcileInventoryCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * On-demand ops endpoints — mirror what CI or a human might trigger by hand.
 * Wire behind Sanctum in production; the demo leaves them open.
 */
class SyncController extends Controller
{
    public function reconcileNow(): JsonResponse
    {
        Artisan::call(ReconcileInventoryCommand::class);

        return response()->json([
            'ok' => true,
            'output' => Artisan::output(),
        ]);
    }

    public function pendingOrders(): JsonResponse
    {
        // "Pending" = webhook received but not yet successfully synced to ERP.
        // In this schema, that means a job is queued/failing before insert
        // into synced_orders — so we surface failed_jobs relating to orders.
        $failedOrderJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%ForwardOrderToErp%')
            ->count();

        $synced = DB::table('synced_orders')->count();

        return response()->json([
            'ok' => true,
            'failed_order_jobs' => $failedOrderJobs,
            'synced_orders_total' => $synced,
        ]);
    }
}
