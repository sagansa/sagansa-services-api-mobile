<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'created_by',
        'customer_id',
        'customer_name',
        'table_code',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'service_total',
        'grand_total',
        'payment_type_id',
        'payment_method',
        'paid_at',
        'source',
        'device_identifier',
        'is_offline',
        'synced_at',
        'order_type',
        'table_id',
        'customer_type_id',
        'proof_of_payment',
        'payment_snapshot',
        'customer_type_snapshot',
    ];

    protected $appends = [
        'receipt_number',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'store_id' => 'string',
        'created_by' => 'string',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'service_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'payment_type_id' => 'string',
        'paid_at' => 'datetime',
        'is_offline' => 'boolean',
        'synced_at' => 'datetime',
        'payment_snapshot' => 'array',
        'customer_type_snapshot' => 'array',
    ];

    protected $dates = [
        'paid_at',
        'synced_at',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function printerJobs()
    {
        return $this->hasMany(PrinterJob::class);
    }

    /**
     * Scopes
     */
    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopePaid($query)
    {
        return $query->whereNotNull('paid_at');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNull('paid_at');
    }

    public function scopeOffline($query)
    {
        return $query->where('is_offline', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_offline', false);
    }

    public function scopeSynced($query)
    {
        return $query->whereNotNull('synced_at');
    }

    public function scopeUnsynced($query)
    {
        return $query->whereNull('synced_at');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Status methods
     */
    public function isPaid()
    {
        return !is_null($this->paid_at);
    }

    public function isUnpaid()
    {
        return is_null($this->paid_at);
    }

    public function isOffline()
    {
        return $this->is_offline === true;
    }

    public function isOnline()
    {
        return $this->is_offline === false;
    }

    public function isSynced()
    {
        return !is_null($this->synced_at);
    }

    public function isUnsynced()
    {
        return is_null($this->synced_at);
    }

    /**
     * Order status methods
     */
    public function markAsPaid()
    {
        $this->update(['paid_at' => now()]);
    }

    public function markAsUnpaid()
    {
        $this->update(['paid_at' => null]);
    }

    public function markAsSynced()
    {
        $this->update(['synced_at' => now()]);
    }

    public function updateTotals()
    {
        $this->subtotal = $this->orderItems()->sum('subtotal');
        $this->tax_total = $this->orderItems()->sum('tax_amount');
        $this->service_total = $this->orderItems()->sum('service_amount');
        $this->discount_total = $this->orderItems()->sum('discount_amount');
        $this->grand_total = $this->subtotal + $this->tax_total + $this->service_total - $this->discount_total;
        $this->save();
    }

    /**
     * Accessors
     */
    public function getReceiptNumberAttribute()
    {
        return substr($this->id, 0, 8);
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals()
    {
        $this->updateTotals();
    }
}