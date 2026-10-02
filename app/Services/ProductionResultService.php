<?php

namespace App\Services;

use App\Exceptions\ProductionOrderNotRunningException;
use App\Models\ProductionResult;
use App\Models\WorkOrder;
use Carbon\Carbon;

class ProductionResultService
{
    /**
     * Simpan hasil produksi untuk WO yang sedang RUNNING.
     * Waktu aktual default: jam jadwal WO pada production_date.
     *
     * @param  array{wo_number:string,qty_good:int,qty_reject:int,production_date:string,actual_start?:string,actual_finish?:string}  $data
     *
     * @throws ProductionOrderNotRunningException
     */
    public function store(array $data): ProductionResult
    {
        $workOrder = WorkOrder::findOrFail($data['wo_number']);

        if (! $workOrder->isRunning()) {
            throw new ProductionOrderNotRunningException($workOrder->wo_number, $workOrder->status);
        }

        $planStart = Carbon::parse($workOrder->plan_start);
        $planFinish = Carbon::parse($workOrder->plan_finish);

        $start = isset($data['actual_start'])
            ? Carbon::parse($data['actual_start'])
            : Carbon::parse($data['production_date'])->setTimeFrom($planStart);
        $finish = isset($data['actual_finish'])
            ? Carbon::parse($data['actual_finish'])
            : $start->copy()->addSeconds($planStart->diffInSeconds($planFinish));

        return ProductionResult::create([
            'wo_number' => $workOrder->wo_number,
            'actual_start' => $start->toDateTimeString(),
            'actual_finish' => $finish->toDateTimeString(),
            'runtime_minutes' => $start->diffInMinutes($finish),
            'good_qty' => $data['qty_good'],
            'reject_qty' => $data['qty_reject'],
            'achievement' => DashboardService::achievement($data['qty_good'], $workOrder->target_qty),
        ]);
    }
}
