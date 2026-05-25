<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'refund_id',
        'order_item_id',
        'quantity_refunded',
        'unit_price',
        'total_refund_amount',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_refunded' => 'integer',
            'unit_price' => 'decimal:2',
            'total_refund_amount' => 'decimal:2',
        ];
    }

    /**
     * Relationships
     */
    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Scopes
     */
    public function scopeByRefund($query, string $refundId)
    {
        return $query->where('refund_id', $refundId);
    }

    public function scopeByOrderItem($query, string $orderItemId)
    {
        return $query->where('order_item_id', $orderItemId);
    }

    /**
     * Calculate total refund amount for this item
     */
    public function calculateTotal(): float
    {
        return $this->quantity_refunded * $this->unit_price;
    }
}
