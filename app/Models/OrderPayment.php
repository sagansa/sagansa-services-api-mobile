<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderPayment extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_payments';

    /**
     * Payment status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIAL_REFUND = 'partial_refund';

    /**
     * Payment method constants
     */
    const METHOD_CASH = 'cash';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_QRIS = 'qris';
    const METHOD_CARD = 'card';
    const METHOD_EWALLET = 'ewallet';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'amount',
        'payment_type_id',
        'reference',
        'status',
        'captured_at',
        'is_offline',
        'synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'captured_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_offline' => 'boolean',
        ];
    }

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Scopes
     */
    public function scopeByOrder($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopeExpired($query)
    {
        return $query->where('expired_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expired_at')
                ->orWhere('expired_at', '>', now());
        });
    }

    /**
     * Check if payment is paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if payment is refunded
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Check if payment is partially refunded
     */
    public function isPartialRefund(): bool
    {
        return $this->status === self::STATUS_PARTIAL_REFUND;
    }

    /**
     * Check if payment has expired
     */
    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast();
    }

    /**
     * Check if payment is active (not expired)
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get net amount (amount - fee)
     */
    public function getNetAmount(): float
    {
        return $this->amount - $this->fee;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2, ',', '.');
    }

    /**
     * Get formatted fee
     */
    public function getFormattedFee(): string
    {
        return number_format($this->fee, 2, ',', '.');
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAmount(): string
    {
        return number_format($this->total_amount, 2, ',', '.');
    }

    /**
     * Get formatted net amount
     */
    public function getFormattedNetAmount(): string
    {
        return number_format($this->getNetAmount(), 2, ',', '.');
    }

    /**
     * Get payment status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu Pembayaran',
            self::STATUS_PAID => 'Dibayar',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_REFUNDED => 'Dikembalikan',
            self::STATUS_PARTIAL_REFUND => 'Pengembalian Sebagian',
            default => 'Tidak Diketahui',
        };
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CASH => 'Tunai',
            self::METHOD_TRANSFER => 'Transfer Bank',
            self::METHOD_QRIS => 'QRIS',
            self::METHOD_CARD => 'Kartu',
            self::METHOD_EWALLET => 'E-Wallet',
            default => 'Tidak Diketahui',
        };
    }

    /**
     * Mark payment as paid
     */
    public function markAsPaid(string $referenceNumber = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'reference_number' => $referenceNumber,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'notes' => $notes,
        ]);
    }

    /**
     * Mark payment as refunded
     */
    public function markAsRefunded(string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
            'notes' => $notes,
        ]);
    }

    /**
     * Mark payment as partial refund
     */
    public function markAsPartialRefund(string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_PARTIAL_REFUND,
            'notes' => $notes,
        ]);
    }

    /**
     * Get available payment statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Menunggu Pembayaran',
            self::STATUS_PAID => 'Dibayar',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_REFUNDED => 'Dikembalikan',
            self::STATUS_PARTIAL_REFUND => 'Pengembalian Sebagian',
        ];
    }

    /**
     * Get available payment methods
     */
    public static function getAvailablePaymentMethods(): array
    {
        return [
            self::METHOD_CASH => 'Tunai',
            self::METHOD_TRANSFER => 'Transfer Bank',
            self::METHOD_QRIS => 'QRIS',
            self::METHOD_CARD => 'Kartu',
            self::METHOD_EWALLET => 'E-Wallet',
        ];
    }

    /**
     * Get payment details for receipt/invoice
     */
    public function getReceiptDetails(): array
    {
        return [
            'method' => $this->getPaymentMethodLabel(),
            'status' => $this->getStatusLabel(),
            'amount' => $this->amount,
            'fee' => $this->fee,
            'total_amount' => $this->total_amount,
            'net_amount' => $this->getNetAmount(),
            'reference' => $this->reference_number,
            'paid_at' => $this->paid_at,
            'formatted_amount' => $this->getFormattedAmount(),
            'formatted_fee' => $this->getFormattedFee(),
            'formatted_total' => $this->getFormattedTotalAmount(),
            'formatted_net' => $this->getFormattedNetAmount(),
        ];
    }
}