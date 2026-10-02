<?php

namespace App\Services;

use App\Models\WorkOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductionOrderService
{
    private const SORTABLE = [
        'wo_number' => 'work_order.wo_number',
        'product' => 'product.product_name',
        'machine' => 'machine.machine_name',
        'operator' => 'employee.full_name',
        'shift' => 'work_order.shift',
        'status' => 'work_order.status',
        'target_qty' => 'work_order.target_qty',
        'plan_start' => 'work_order.plan_start',
        'plan_finish' => 'work_order.plan_finish',
        'good_qty' => 'good_qty',
        'reject_qty' => 'reject_qty',
    ];

    /**
     * @param  array{search?:string,product?:string,machine?:string,status?:string,date?:string,sort_by?:string,sort_dir?:string,per_page?:int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return WorkOrder::query()
            ->withDetail()
            ->filter($filters)
            ->orderBy(self::SORTABLE[$filters['sort_by'] ?? 'plan_start'], $filters['sort_dir'] ?? 'desc')
            ->orderBy('work_order.wo_number')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}
