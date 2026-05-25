<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductStorePivot extends Pivot
{
    protected $fillable = [
        'price',
        'is_available',
        'stock_quantity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'stock_quantity' => 'integer',
    ];
}