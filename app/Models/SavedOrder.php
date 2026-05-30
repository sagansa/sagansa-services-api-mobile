<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedOrder extends Model
{
    use HasFactory, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',
        'name',
        'items',
        'total',
        'order_type',
        'table_id',
        'customer_type_id',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
