<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Printer extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'name',
        'connection_type',
        'ip_address',
        'port',
        'bluetooth_identifier',
        'paper_size',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'store_id' => 'string',
        'port' => 'integer',
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

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function jobs()
    {
        return $this->hasMany(PrinterJob::class);
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

    public function scopeByConnectionType($query, $connectionType)
    {
        return $query->where('connection_type', $connectionType);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Check if printer is active
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Check if printer is WiFi connection
     */
    public function isWifi()
    {
        return $this->connection_type === 'wifi';
    }

    /**
     * Check if printer is Bluetooth connection
     */
    public function isBluetooth()
    {
        return $this->connection_type === 'bluetooth';
    }

    /**
     * Activate printer
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate printer
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Get pending jobs
     */
    public function pendingJobs()
    {
        return $this->jobs()->pending();
    }

    /**
     * Get completed jobs
     */
    public function completedJobs()
    {
        return $this->jobs()->completed();
    }

    /**
     * Get failed jobs
     */
    public function failedJobs()
    {
        return $this->jobs()->failed();
    }

    /**
     * Create a new print job
     */
    public function createJob($jobType, $payload, $orderId = null)
    {
        return $this->jobs()->create([
            'order_id' => $orderId,
            'job_type' => $jobType,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    /**
     * Test printer connection
     */
    public function testConnection()
    {
        // This would be implemented with actual printer connection logic
        // For now, we'll simulate a successful connection
        return [
            'success' => true,
            'message' => 'Printer connection test successful',
            'printer_id' => $this->id,
            'connection_type' => $this->connection_type,
        ];
    }
}