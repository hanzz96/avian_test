<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Downtime extends BaseModel
{
    protected $table = 'downtime';

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'wo_number');
    }
}
