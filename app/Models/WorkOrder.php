<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WorkOrder extends BaseModel
{
    public const STATUS_RUNNING = 'RUNNING';

    protected $table = 'work_order';

    protected $primaryKey = 'wo_number';

    public $incrementing = false;

    protected $keyType = 'string';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_code');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_no');
    }

    public function productionResults(): HasMany
    {
        return $this->hasMany(ProductionResult::class, 'wo_number');
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Data siap tampil untuk list: join produk/mesin/operator + total good/reject dari hasil produksi.
     * Satu baris per WO (digroup), jadi aman dipaginasi.
     */
    public function scopeWithDetail(Builder $query): Builder
    {
        return $query
            ->join('product', 'product.product_code', '=', 'work_order.product_code')
            ->join('machine', 'machine.machine_code', '=', 'work_order.machine_code')
            ->join('employee', 'employee.employee_no', '=', 'work_order.employee_no')
            ->leftJoin('production_result', 'production_result.wo_number', '=', 'work_order.wo_number')
            ->groupBy(
                'work_order.wo_number', 'work_order.shift', 'work_order.target_qty', 'work_order.plan_start',
                'work_order.plan_finish', 'work_order.status', 'product.product_code', 'product.product_name',
                'machine.machine_code', 'machine.machine_name', 'employee.employee_no', 'employee.full_name'
            )
            ->select([
                'work_order.wo_number', 'work_order.shift', 'work_order.target_qty', 'work_order.plan_start',
                'work_order.plan_finish', 'work_order.status', 'product.product_code', 'product.product_name',
                'machine.machine_code', 'machine.machine_name', 'employee.employee_no',
                'employee.full_name as operator_name',
                DB::raw('COALESCE(SUM(production_result.good_qty),0) as good_qty'),
                DB::raw('COALESCE(SUM(production_result.reject_qty),0) as reject_qty'),
            ]);
    }

    /**
     * Filter list. Wajib dipakai bersama scopeWithDetail() (butuh join-nya).
     *
     * @param  array{search?:string,product?:string,machine?:string,status?:string,date?:string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $like = '%'.addcslashes($filters['search'], '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('work_order.wo_number', 'like', $like)
                    ->orWhere('product.product_name', 'like', $like)
                    ->orWhere('machine.machine_name', 'like', $like)
                    ->orWhere('employee.full_name', 'like', $like));
            })
            ->when(! empty($filters['product']), fn (Builder $q) => $q->where('work_order.product_code', $filters['product']))
            ->when(! empty($filters['machine']), fn (Builder $q) => $q->where('work_order.machine_code', $filters['machine']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->whereIn(
                'work_order.status',
                array_map('strtoupper', explode(',', $filters['status']))
            ))
            ->when(! empty($filters['date']), fn (Builder $q) => $q->whereDate('work_order.plan_start', $filters['date']));
    }
}
