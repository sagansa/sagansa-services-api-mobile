<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentType extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderPayments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if payment type is active
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Activate payment type
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate payment type
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}