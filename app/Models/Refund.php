<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    /**
     * Refund type constants
     */
    const TYPE_FULL = 'full';
    const TYPE_PARTIAL = 'partial';

    /**
     * Refund status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'order_id',
        'refund_number',
        'refund_type',
        'total_amount',
        'reason',
        'notes',
        'refunded_by',
        'refunded_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'payment_method',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function refundItems()
    {
        return $this->hasMany(RefundItem::class);
    }

    public function refundedBy()
    {
        return $this->belongsTo(User::class, 'refunded_by', 'uuid');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'uuid');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by', 'uuid');
    }

    /**
     * Scopes
     */
    public function scopeByOrder($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('refund_type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByStore($query, string $storeId)
    {
        return $query->whereHas('order', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        });
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('refunded_at', [$startDate, $endDate]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Helper methods
     */
    public function isFullRefund(): bool
    {
        return $this->refund_type === self::TYPE_FULL;
    }

    public function isPartialRefund(): bool
    {
        return $this->refund_type === self::TYPE_PARTIAL;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Generate unique refund number
     */
    public static function generateRefundNumber(): string
    {
        $date = now()->format('Ymd');
        $lastRefund = self::whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastRefund && preg_match('/REF-\d{8}-(\d{3})/', $lastRefund->refund_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('REF-%s-%03d', $date, $sequence);
    }

    /**
     * Calculate total amount from refund items
     */
    public function calculateTotalAmount(): float
    {
        return $this->refundItems()->sum('total_refund_amount');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('tenant')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * Get available refund types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_FULL => 'Full Refund',
            self::TYPE_PARTIAL => 'Partial Refund',
        ];
    }

    /**
     * Get available statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }
}
