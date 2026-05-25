<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreUser extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',
        'role',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'store_id' => 'string',
        'user_id' => 'string',
        'role' => 'string',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if store-user relationship is active
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Activate store-user relationship
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate store-user relationship
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}