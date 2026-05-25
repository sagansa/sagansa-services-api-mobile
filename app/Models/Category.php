<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Category extends Model
{
    use HasFactory, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
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
     * Check if category is active
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Activate category
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate category
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}