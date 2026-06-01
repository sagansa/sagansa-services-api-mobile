<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\TenantScope;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'store_id', // Added for proper data segregation
        'quantity',
        'quantity_refunded',
        'unit_price',
        'total_price',
        'refund_amount',
        'discount_amount',
        'tax_amount',
        'notes',
        'is_custom_price',
        'is_free_item',
        'product_snapshot',
        'variant_snapshot',
        'modifications_snapshot',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_refunded' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'is_custom_price' => 'boolean',
            'is_free_item' => 'boolean',
            'product_snapshot' => 'array',
            'variant_snapshot' => 'array',
            'modifications_snapshot' => 'array',
        ];
    }

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItemModifications()
    {
        return $this->hasMany(OrderItemModification::class, 'order_item_id');
    }

    public function refundItems()
    {
        return $this->hasMany(RefundItem::class);
    }

    /**
     * Scopes
     */
    public function scopeByOrder($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByProductVariant($query, string $productVariantId)
    {
        return $query->where('product_variant_id', $productVariantId);
    }

    public function scopeByOrderStatus($query, string $status)
    {
        return $query->whereHas('order', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    public function scopeByStore($query, string $storeId)
    {
        return $query->whereHas('order', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        });
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        });
    }

    public function scopeByCustomer($query, string $customerId)
    {
        return $query->whereHas('order', function ($q) use ($customerId) {
            $q->where('customer_id', $customerId);
        });
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByPaymentStatus($query, string $paymentStatus)
    {
        return $query->whereHas('order', function ($q) use ($paymentStatus) {
            $q->where('payment_status', $paymentStatus);
        });
    }

    public function scopeByPaymentMethod($query, string $paymentMethod)
    {
        return $query->whereHas('order', function ($q) use ($paymentMethod) {
            $q->where('payment_method', $paymentMethod);
        });
    }

    public function scopeByOrderType($query, string $orderType)
    {
        return $query->whereHas('order', function ($q) use ($orderType) {
            $q->where('order_type', $orderType);
        });
    }

    public function scopeByOrderChannel($query, string $orderChannel)
    {
        return $query->whereHas('order', function ($q) use ($orderChannel) {
            $q->where('order_channel', $orderChannel);
        });
    }

    public function scopeByOrderSource($query, string $orderSource)
    {
        return $query->whereHas('order', function ($q) use ($orderSource) {
            $q->where('order_source', $orderSource);
        });
    }

    public function scopeByOrderPriority($query, string $orderPriority)
    {
        return $query->whereHas('order', function ($q) use ($orderPriority) {
            $q->where('order_priority', $orderPriority);
        });
    }

    public function scopeByOrderTag($query, string $orderTag)
    {
        return $query->whereHas('order', function ($q) use ($orderTag) {
            $q->where('order_tag', $orderTag);
        });
    }

    public function scopeByOrderNote($query, string $orderNote)
    {
        return $query->whereHas('order', function ($q) use ($orderNote) {
            $q->where('order_note', 'like', '%' . $orderNote . '%');
        });
    }

    public function scopeByOrderCustomerNote($query, string $orderCustomerNote)
    {
        return $query->whereHas('order', function ($q) use ($orderCustomerNote) {
            $q->where('customer_note', 'like', '%' . $orderCustomerNote . '%');
        });
    }

    public function scopeByOrderStaffNote($query, string $orderStaffNote)
    {
        return $query->whereHas('order', function ($q) use ($orderStaffNote) {
            $q->where('staff_note', 'like', '%' . $orderStaffNote . '%');
        });
    }

    public function scopeByOrderDiscountCode($query, string $orderDiscountCode)
    {
        return $query->whereHas('order', function ($q) use ($orderDiscountCode) {
            $q->where('discount_code', 'like', '%' . $orderDiscountCode . '%');
        });
    }

    public function scopeByOrderDiscountAmount($query, float $orderDiscountAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderDiscountAmount) {
            $q->where('discount_amount', $orderDiscountAmount);
        });
    }

    public function scopeByOrderTaxAmount($query, float $orderTaxAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderTaxAmount) {
            $q->where('tax_amount', $orderTaxAmount);
        });
    }

    public function scopeByOrderShippingAmount($query, float $orderShippingAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderShippingAmount) {
            $q->where('shipping_amount', $orderShippingAmount);
        });
    }

    public function scopeByOrderSubtotal($query, float $orderSubtotal)
    {
        return $query->whereHas('order', function ($q) use ($orderSubtotal) {
            $q->where('subtotal', $orderSubtotal);
        });
    }

    public function scopeByOrderTotal($query, float $orderTotal)
    {
        return $query->whereHas('order', function ($q) use ($orderTotal) {
            $q->where('total', $orderTotal);
        });
    }

    public function scopeByOrderPaidAmount($query, float $orderPaidAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderPaidAmount) {
            $q->where('paid_amount', $orderPaidAmount);
        });
    }

    public function scopeByOrderChangeAmount($query, float $orderChangeAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderChangeAmount) {
            $q->where('change_amount', $orderChangeAmount);
        });
    }

    public function scopeByOrderRefundAmount($query, float $orderRefundAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundAmount) {
            $q->where('refund_amount', $orderRefundAmount);
        });
    }

    public function scopeByOrderRefundStatus($query, string $orderRefundStatus)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundStatus) {
            $q->where('refund_status', $orderRefundStatus);
        });
    }

    public function scopeByOrderRefundMethod($query, string $orderRefundMethod)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundMethod) {
            $q->where('refund_method', $orderRefundMethod);
        });
    }

    public function scopeByOrderRefundNote($query, string $orderRefundNote)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundNote) {
            $q->where('refund_note', 'like', '%' . $orderRefundNote . '%');
        });
    }

    public function scopeByOrderRefundReason($query, string $orderRefundReason)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundReason) {
            $q->where('refund_reason', 'like', '%' . $orderRefundReason . '%');
        });
    }

    public function scopeByOrderRefundBy($query, string $orderRefundBy)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundBy) {
            $q->where('refund_by', $orderRefundBy);
        });
    }

    public function scopeByOrderRefundAt($query, string $orderRefundAt)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundAt) {
            $q->where('refund_at', $orderRefundAt);
        });
    }

    public function scopeByOrderRefundApprovedBy($query, string $orderRefundApprovedBy)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundApprovedBy) {
            $q->where('refund_approved_by', $orderRefundApprovedBy);
        });
    }

    public function scopeByOrderRefundApprovedAt($query, string $orderRefundApprovedAt)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundApprovedAt) {
            $q->where('refund_approved_at', $orderRefundApprovedAt);
        });
    }

    public function scopeByOrderRefundRejectedBy($query, string $orderRefundRejectedBy)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundRejectedBy) {
            $q->where('refund_rejected_by', $orderRefundRejectedBy);
        });
    }

    public function scopeByOrderRefundRejectedAt($query, string $orderRefundRejectedAt)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundRejectedAt) {
            $q->where('refund_rejected_at', $orderRefundRejectedAt);
        });
    }

    public function scopeByOrderRefundRejectedReason($query, string $orderRefundRejectedReason)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundRejectedReason) {
            $q->where('refund_rejected_reason', 'like', '%' . $orderRefundRejectedReason . '%');
        });
    }

    public function scopeByOrderRefundProcessedBy($query, string $orderRefundProcessedBy)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedBy) {
            $q->where('refund_processed_by', $orderRefundProcessedBy);
        });
    }

    public function scopeByOrderRefundProcessedAt($query, string $orderRefundProcessedAt)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAt) {
            $q->where('refund_processed_at', $orderRefundProcessedAt);
        });
    }

    public function scopeByOrderRefundProcessedNote($query, string $orderRefundProcessedNote)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedNote) {
            $q->where('refund_processed_note', 'like', '%' . $orderRefundProcessedNote . '%');
        });
    }

    public function scopeByOrderRefundProcessedReason($query, string $orderRefundProcessedReason)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedReason) {
            $q->where('refund_processed_reason', 'like', '%' . $orderRefundProcessedReason . '%');
        });
    }

    public function scopeByOrderRefundProcessedStatus($query, string $orderRefundProcessedStatus)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedStatus) {
            $q->where('refund_processed_status', $orderRefundProcessedStatus);
        });
    }

    public function scopeByOrderRefundProcessedAmount($query, float $orderRefundProcessedAmount)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAmount) {
            $q->where('refund_processed_amount', $orderRefundProcessedAmount);
        });
    }

    public function scopeByOrderRefundProcessedMethod($query, string $orderRefundProcessedMethod)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedMethod) {
            $q->where('refund_processed_method', $orderRefundProcessedMethod);
        });
    }

    public function scopeByOrderRefundProcessedByUser($query, string $orderRefundProcessedByUser)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedByUser) {
            $q->where('refund_processed_by_user', $orderRefundProcessedByUser);
        });
    }

    public function scopeByOrderRefundProcessedAtDate($query, string $orderRefundProcessedAtDate)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtDate) {
            $q->where('refund_processed_at_date', $orderRefundProcessedAtDate);
        });
    }

    public function scopeByOrderRefundProcessedAtTime($query, string $orderRefundProcessedAtTime)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtTime) {
            $q->where('refund_processed_at_time', $orderRefundProcessedAtTime);
        });
    }

    public function scopeByOrderRefundProcessedAtDateTime($query, string $orderRefundProcessedAtDateTime)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtDateTime) {
            $q->where('refund_processed_at_date_time', $orderRefundProcessedAtDateTime);
        });
    }

    public function scopeByOrderRefundProcessedAtTimestamp($query, string $orderRefundProcessedAtTimestamp)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtTimestamp) {
            $q->where('refund_processed_at_timestamp', $orderRefundProcessedAtTimestamp);
        });
    }

    public function scopeByOrderRefundProcessedAtDateTimeString($query, string $orderRefundProcessedAtDateTimeString)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtDateTimeString) {
            $q->where('refund_processed_at_date_time_string', $orderRefundProcessedAtDateTimeString);
        });
    }

    public function scopeByOrderRefundProcessedAtDateTimeObject($query, string $orderRefundProcessedAtDateTimeObject)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtDateTimeObject) {
            $q->where('refund_processed_at_date_time_object', $orderRefundProcessedAtDateTimeObject);
        });
    }

    public function scopeByOrderRefundProcessedAtDateTimeArray($query, string $orderRefundProcessedAtDateTimeArray)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtDateTimeArray) {
            $q->where('refund_processed_at_date_time_array', $orderRefundProcessedAtDateTimeArray);
        });
    }

    public function scopeByOrderRefundProcessedAtDateTimeCollection($query, string $orderRefundProcessedAtDateTimeCollection)
    {
        return $query->whereHas('order', function ($q) use ($orderRefundProcessedAtDateTimeCollection) {
            $q->where('refund_processed_at_date_time_collection', $orderRefundProcessedAtDateTimeCollection);
        });
    }

}
