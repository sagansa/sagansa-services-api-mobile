<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'store_id',
        'check_in_store_id',
        'check_out_store_id',
        'shift_store_id',
        'status',
        'was_late',
        'image_in',
        'check_in',
        'latitude_in',
        'longitude_in',
        'image_out',
        'check_out',
        'latitude_out',
        'longitude_out',
        'created_by_id',
        'approved_by_id',
    ];

    protected $casts = [
        'id' => 'string',
        'store_id' => 'string',
        'check_in_store_id' => 'string',
        'check_out_store_id' => 'string',
        'shift_store_id' => 'string',
        'latitude_in' => 'decimal:7',
        'longitude_in' => 'decimal:7',
        'latitude_out' => 'decimal:7',
        'longitude_out' => 'decimal:7',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'created_by_id' => 'string',
        'approved_by_id' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function shiftStore()
    {
        return $this->belongsTo(ShiftStore::class, 'shift_store_id');
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by_id', $userId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('check_in', [$startDate, $endDate]);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function isCheckedIn()
    {
        return !is_null($this->check_in_at);
    }

    public function isCheckedOut()
    {
        return !is_null($this->check_out);
    }

    public function getDurationAttribute()
    {
        if ($this->check_in && $this->check_out) {
            return $this->check_in->diffInMinutes($this->check_out);
        }
        return null;
    }
}
