<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'abbreviation',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
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

    /**
     * Check if unit is active
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Activate unit
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate unit
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}