<?php

namespace App\Services;

use App\Models\Downtime;
use App\Models\Machine;
use App\Models\ProductionResult;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DashboardService
{
    private const STATUSES = ['RUNNING', 'FINISHED', 'OPEN', 'CANCELLED'];

    public static function achievement(float|int|null $good, float|int|null $target): float
    {
        return $target > 0 ? round($good / $target * 100, 2) : 0.0;
    }

    public function dashboard(): array
    {
        $latest = $this->latestDate();

        return [
            'summary' => $this->summary($latest),
            'trend_7_days' => $this->trend($latest, 7),
            'status_breakdown' => $this->statusBreakdown(),
            'top_machines' => $this->topMachines(10),
        ];
    }

    public function machine(string $machineCode): array
    {
        $machine = Machine::find($machineCode);
        abort_if(! $machine, 404, 'Machine not found');

        $totalOrder = $machine->workOrders()->count();

        $result = ProductionResult::withWorkOrder()
            ->where('work_order.machine_code', $machineCode)
            ->selectRaw('COALESCE(SUM(production_result.good_qty),0) good, COALESCE(SUM(production_result.reject_qty),0) reject, COALESCE(SUM(work_order.target_qty),0) target')
            ->first();

        $downtime = Downtime::query()
            ->join('work_order', 'work_order.wo_number', '=', 'downtime.wo_number')
            ->where('work_order.machine_code', $machineCode)
            ->sum('downtime.duration_minutes');

        return [
            'machine_name' => $machine->machine_name,
            'total_order' => $totalOrder,
            'good_qty' => (int) $result->good,
            'reject_qty' => (int) $result->reject,
            'downtime_minutes' => (int) $downtime,
            'achievement' => self::achievement($result->good, $result->target)
        ];
    }

    private function latestDate(): CarbonImmutable
    {
        $max = ProductionResult::max('actual_start');

        return CarbonImmutable::parse($max ?? now())->startOfDay();
    }

    private function summary(CarbonImmutable $day): array
    {
        $today = $this->dailyTotals($day, $day)[$day->toDateString()] ?? null;
        $good = (int) ($today->good ?? 0);
        $target = (int) ($today->target ?? 0);

        $status = $this->countByStatus();

        return [
            'total_machine' => Machine::count(),
            'running_order' => (int) ($status['RUNNING'] ?? 0),
            'finished_order' => (int) ($status['FINISHED'] ?? 0),
            'today_target' => $target,
            'today_good' => $good,
            'today_reject' => (int) ($today->reject ?? 0),
            'achievement' => self::achievement($good, $target),
        ];
    }

    private function trend(CarbonImmutable $end, int $days): array
    {
        $start = $end->subDays($days - 1);
        $totals = $this->dailyTotals($start, $end);

        $rows = [];
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $t = $totals[$d->toDateString()] ?? null;
            $rows[] = [
                'date' => $d->toDateString(),
                'good_qty' => (int) ($t->good ?? 0),
                'reject_qty' => (int) ($t->reject ?? 0),
                'achievement' => self::achievement($t->good ?? 0, $t->target ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, object{good:int,reject:int,target:int}>
     */
    private function dailyTotals(Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): array
    {
        return ProductionResult::withWorkOrder()
            ->whereBetween('production_result.actual_start', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(production_result.actual_start) d, SUM(production_result.good_qty) good, SUM(production_result.reject_qty) reject, SUM(work_order.target_qty) target')
            ->groupByRaw('DATE(production_result.actual_start)')
            ->get()
            ->keyBy('d')
            ->all();
    }

    private function statusBreakdown(): array
    {
        $counts = $this->countByStatus();

        return array_map(fn($s) => ['status' => $s, 'total' => (int) ($counts[$s] ?? 0)], self::STATUSES);
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function countByStatus()
    {
        return WorkOrder::selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');
    }

    private function topMachines(int $limit): array
    {
        return ProductionResult::withWorkOrder()
            ->join('machine', 'machine.machine_code', '=', 'work_order.machine_code')
            ->groupBy('machine.machine_code', 'machine.machine_name')
            ->orderByDesc('good_qty')
            ->orderBy('machine.machine_code')
            ->limit($limit)
            ->selectRaw('machine.machine_code, machine.machine_name, SUM(production_result.good_qty) good_qty, SUM(work_order.target_qty) target')
            ->get()
            ->map(fn($r) => [
                'machine_code' => $r->machine_code,
                'machine_name' => $r->machine_name,
                'good_qty' => (int) $r->good_qty,
                'achievement' => self::achievement($r->good_qty, $r->target),
            ])
            ->all();
    }
}
