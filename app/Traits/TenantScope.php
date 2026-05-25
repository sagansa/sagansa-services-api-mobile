<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TenantScope
{
    /**
     * Boot the tenant scope trait
     */
    protected static function bootTenantScope()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::hasUser() && Auth::user()->tenant_id) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', Auth::user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (Auth::hasUser() && Auth::user()->tenant_id && !$model->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Get records for all tenants (bypass tenant scope)
     */
    public static function withoutTenantScope()
    {
        return static::withoutGlobalScope('tenant');
    }

    /**
     * Get records for a specific tenant
     */
    public static function forTenant($tenantId)
    {
        return static::withoutTenantScope()->where('tenant_id', $tenantId);
    }
}