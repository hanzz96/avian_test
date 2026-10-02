<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends BaseModel
{
    protected $table = 'employee';

    protected $primaryKey = 'employee_no';

    public $incrementing = false;

    protected $keyType = 'string';

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'employee_no');
    }
}
