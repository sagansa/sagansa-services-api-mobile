<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftStore extends Model
{
    use HasFactory, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'shift_start_time',
        'shift_end_time',
        'duration',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'shift_start_time' => 'datetime:H:i',
        'shift_end_time' => 'datetime:H:i',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('duration', '>', 0);
    }

    public function scopeInactive($query)
    {
        return $query->where(function ($q) {
            $q->where('duration', '<=', 0)->orWhereNull('duration');
        });
    }

    /**
     * Check if shift is active
     */
    public function isActive()
    {
        return $this->duration > 0;
    }

    /**
     * Check if shift is currently active
     */
    public function isCurrentlyActive()
    {
        $now = now();
        return $this->is_active && 
               $now->between($this->start_time, $this->end_time);
    }

    /**
     * Activate shift
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate shift
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}
