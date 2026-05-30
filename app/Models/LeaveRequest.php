<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'leaves';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'start_date',
        'end_date',
        'duration',
        'reason',
        'status',
        'approved_by_id',
        'approved_at',
        'rejected_at',
        'review_notes',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'tenant_id' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'duration' => 'integer',
        'approved_by_id' => 'string',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'approved_at',
        'rejected_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_id', 'uuid');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function getDurationDaysAttribute()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
