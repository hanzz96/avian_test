<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductionOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductionOrderController extends Controller
{
    private const SORTABLE = [
        'wo_number' => 'w.wo_number',
        'product' => 'pr.product_name',
        'machine' => 'm.machine_name',
        'operator' => 'e.full_name',
        'shift' => 'w.shift',
        'status' => 'w.status',
        'target_qty' => 'w.target_qty',
        'plan_start' => 'w.plan_start',
        'plan_finish' => 'w.plan_finish',
        'good_qty' => 'good_qty',
        'reject_qty' => 'reject_qty',
    ];

    public function index(ListProductionOrderRequest $request): JsonResponse
    {
        $v = $request->validated();

        $query = DB::table('work_order as w')
            ->join('product as pr', 'pr.product_code', '=', 'w.product_code')
            ->join('machine as m', 'm.machine_code', '=', 'w.machine_code')
            ->join('employee as e', 'e.employee_no', '=', 'w.employee_no')
            ->leftJoin('production_result as p', 'p.wo_number', '=', 'w.wo_number')
            ->groupBy(
                'w.wo_number', 'w.shift', 'w.target_qty', 'w.plan_start', 'w.plan_finish', 'w.status',
                'pr.product_code', 'pr.product_name', 'm.machine_code', 'm.machine_name', 'e.employee_no', 'e.full_name'
            )
            ->select([
                'w.wo_number', 'w.shift', 'w.target_qty', 'w.plan_start', 'w.plan_finish', 'w.status',
                'pr.product_code', 'pr.product_name', 'm.machine_code', 'm.machine_name',
                'e.employee_no', 'e.full_name as operator_name',
                DB::raw('COALESCE(SUM(p.good_qty),0) as good_qty'),
                DB::raw('COALESCE(SUM(p.reject_qty),0) as reject_qty'),
            ]);

        if (! empty($v['search'])) {
            $like = '%'.addcslashes($v['search'], '%_\\').'%';
            $query->where(fn ($q) => $q
                ->where('w.wo_number', 'like', $like)
                ->orWhere('pr.product_name', 'like', $like)
                ->orWhere('m.machine_name', 'like', $like)
                ->orWhere('e.full_name', 'like', $like));
        }
        if (! empty($v['product'])) {
            $query->where('w.product_code', $v['product']);
        }
        if (! empty($v['machine'])) {
            $query->where('w.machine_code', $v['machine']);
        }
        if (! empty($v['status'])) {
            $query->whereIn('w.status', array_map('strtoupper', explode(',', $v['status'])));
        }
        if (! empty($v['date'])) {
            $query->whereDate('w.plan_start', $v['date']);
        }

        $sortBy = self::SORTABLE[$v['sort_by'] ?? 'plan_start'];
        $query->orderBy($sortBy, $v['sort_dir'] ?? 'desc')->orderBy('w.wo_number');

        $page = $query->paginate($v['per_page'] ?? 15)->withQueryString();

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
