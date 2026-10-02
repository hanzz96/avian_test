<?php

namespace App\Http\Controllers;

use App\Exceptions\ProductionOrderNotRunningException;
use App\Http\Requests\StoreProductionResultRequest;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/production-results — simpan hasil produksi.
 * Hanya WO berstatus RUNNING yang boleh menerima hasil.
 */
class ProductionResultController extends Controller
{
    public function store(StoreProductionResultRequest $request): JsonResponse
    {
        $v = $request->validated();

        $wo = DB::table('work_order')->where('wo_number', $v['wo_number'])->first();

        if ($wo->status !== 'RUNNING') {
            throw new ProductionOrderNotRunningException($wo->wo_number, $wo->status);
        }

        // Waktu aktual default: jam jadwal WO pada production_date.
        $planStart = Carbon::parse($wo->plan_start);
        $planFinish = Carbon::parse($wo->plan_finish);
        $start = isset($v['actual_start'])
            ? Carbon::parse($v['actual_start'])
            : Carbon::parse($v['production_date'])->setTimeFrom($planStart);
        $finish = isset($v['actual_finish'])
            ? Carbon::parse($v['actual_finish'])
            : $start->copy()->addSeconds($planStart->diffInSeconds($planFinish));

        $row = [
            'wo_number' => $wo->wo_number,
            'actual_start' => $start->toDateTimeString(),
            'actual_finish' => $finish->toDateTimeString(),
            'runtime_minutes' => $start->diffInMinutes($finish),
            'good_qty' => $v['qty_good'],
            'reject_qty' => $v['qty_reject'],
            'achievement' => DashboardService::achievement($v['qty_good'], $wo->target_qty),
        ];
        $row['id'] = DB::table('production_result')->insertGetId($row);

        return response()->json(['message' => 'Production result saved', 'data' => $row], 201);
    }
}
