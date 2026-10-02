<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    protected $table = 'product';

    protected $primaryKey = 'product_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'product_code');
    }
}
