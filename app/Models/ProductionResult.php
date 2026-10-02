<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionResult extends BaseModel
{
    protected $table = 'production_result';

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'wo_number');
    }

    /**
     * Gabungkan dengan work_order (untuk target_qty / mesin). Dipakai semua agregasi dashboard,
     * sehingga target hanya dihitung dari WO yang sudah punya hasil produksi.
     */
    public function scopeWithWorkOrder(Builder $query): Builder
    {
        return $query->join('work_order', 'work_order.wo_number', '=', 'production_result.wo_number');
    }
}
