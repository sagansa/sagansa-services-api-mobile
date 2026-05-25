<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrinterJob extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'printer_id',
        'order_id',
        'job_type',
        'payload',
        'status',
        'error_message',
        'attempted_at',
        'printed_at',
    ];

    protected $casts = [
        'id' => 'string',
        'printer_id' => 'string',
        'order_id' => 'string',
        'job_type' => 'string',
        'payload' => 'array',
        'status' => 'string',
        'error_message' => 'string',
        'attempted_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    protected $dates = [
        'attempted_at',
        'printed_at',
    ];

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeByPrinter($query, $printerId)
    {
        return $query->where('printer_id', $printerId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePrinting($query)
    {
        return $query->where('status', 'printing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }


    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isPrinting()
    {
        return $this->status === 'printing';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function markAsPrinting()
    {
        $this->update([
            'status' => 'printing',
            'attempted_at' => now(),
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'printed_at' => now(),
        ]);
    }

    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'attempted_at' => now(),
        ]);
    }
}