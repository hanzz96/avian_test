<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Query dashboard. Semua tanggal "hari ini" dihitung relatif terhadap data terbaru
 * (MAX(actual_start)), bukan jam server.
 */
class DashboardService
{
    private const STATUSES = ['RUNNING', 'FINISHED', 'OPEN', 'CANCELLED'];

    /**
     * Achievement = good / target * 100. Target hanya dari WO yang sudah punya hasil produksi.
     */
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
        $machine = DB::table('machine')->where('machine_code', $machineCode)->first();
        abort_if(! $machine, 404, 'Machine not found');

        $totalOrder = DB::table('work_order')->where('machine_code', $machineCode)->count();

        $result = DB::table('production_result as p')
            ->join('work_order as w', 'w.wo_number', '=', 'p.wo_number')
            ->where('w.machine_code', $machineCode)
            ->selectRaw('COALESCE(SUM(p.good_qty),0) good, COALESCE(SUM(p.reject_qty),0) reject, COALESCE(SUM(w.target_qty),0) target')
            ->first();

        $downtime = DB::table('downtime as d')
            ->join('work_order as w', 'w.wo_number', '=', 'd.wo_number')
            ->where('w.machine_code', $machineCode)
            ->sum('d.duration_minutes');

        return [
            'machine_name' => $machine->machine_name,
            'total_order' => $totalOrder,
            'good_qty' => (int) $result->good,
            'reject_qty' => (int) $result->reject,
            'downtime_minutes' => (int) $downtime,
            'achievement' => self::achievement($result->good, $result->target),
        ];
    }

    /** Tanggal terbaru di data (bukan CURDATE()), fallback ke hari ini bila data kosong. */
    private function latestDate(): CarbonImmutable
    {
        $max = DB::table('production_result')->max('actual_start');

        return CarbonImmutable::parse($max ?? now())->startOfDay();
    }

    private function summary(CarbonImmutable $day): array
    {
        $today = $this->dailyTotals($day, $day)[$day->toDateString()] ?? null;
        $good = (int) ($today->good ?? 0);
        $target = (int) ($today->target ?? 0);

        $status = DB::table('work_order')->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');

        return [
            'total_machine' => DB::table('machine')->count(),
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

    /** @return array<string, object{good:int,reject:int,target:int}> keyed by Y-m-d */
    private function dailyTotals(Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): array
    {
        return DB::table('production_result as p')
            ->join('work_order as w', 'w.wo_number', '=', 'p.wo_number')
            ->whereBetween('p.actual_start', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(p.actual_start) d, SUM(p.good_qty) good, SUM(p.reject_qty) reject, SUM(w.target_qty) target')
            ->groupByRaw('DATE(p.actual_start)')
            ->get()
            ->keyBy('d')
            ->all();
    }

    private function statusBreakdown(): array
    {
        $counts = DB::table('work_order')->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');

        return array_map(fn ($s) => ['status' => $s, 'total' => (int) ($counts[$s] ?? 0)], self::STATUSES);
    }

    private function topMachines(int $limit): array
    {
        return DB::table('production_result as p')
            ->join('work_order as w', 'w.wo_number', '=', 'p.wo_number')
            ->join('machine as m', 'm.machine_code', '=', 'w.machine_code')
            ->groupBy('m.machine_code', 'm.machine_name')
            ->orderByDesc('good_qty')
            ->orderBy('m.machine_code')
            ->limit($limit)
            ->selectRaw('m.machine_code, m.machine_name, SUM(p.good_qty) good_qty, SUM(w.target_qty) target')
            ->get()
            ->map(fn ($r) => [
                'machine_code' => $r->machine_code,
                'machine_name' => $r->machine_name,
                'good_qty' => (int) $r->good_qty,
                'achievement' => self::achievement($r->good_qty, $r->target),
            ])
            ->all();
    }
}
