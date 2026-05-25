<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_id',
        'type',
        'name',
        'is_active',
        'details',
        'require_proof',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'require_proof' => 'boolean',
        'details' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
