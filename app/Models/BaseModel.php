<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dasar semua model. Tabel milik test tidak punya created_at/updated_at,
 * dan strukturnya tidak boleh diubah — jadi timestamps dimatikan.
 */
abstract class BaseModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
