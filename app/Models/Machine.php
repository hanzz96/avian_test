<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends BaseModel
{
    protected $table = 'machine';

    protected $primaryKey = 'machine_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'machine_code');
    }
}
