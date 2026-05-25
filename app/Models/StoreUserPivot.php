<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StoreUserPivot extends Pivot
{
    protected $fillable = [
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}