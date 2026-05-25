<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_id',
        'name',
        'is_active',
        'order',
        'auto_payment',
        'linked_payment_method_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_payment' => 'boolean',
        'order' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function linkedPaymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'linked_payment_method_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
