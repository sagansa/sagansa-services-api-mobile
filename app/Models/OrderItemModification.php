<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderItemModification extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_item_modifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'order_item_id',
        'product_modification_id',
        'quantity',
        'price',
        'notes',
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
            'price' => 'decimal:2',
        ];
    }

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function productModification()
    {
        return $this->belongsTo(ProductModification::class, 'product_modification_id');
    }

    /**
     * Scopes
     */
    public function scopeByOrderItem($query, string $orderItemId)
    {
        return $query->where('order_item_id', $orderItemId);
    }

    public function scopeByProductModification($query, string $productModificationId)
    {
        return $query->where('product_modification_id', $productModificationId);
    }

    public function scopeByOrder($query, string $orderId)
    {
        return $query->whereHas('orderItem', function ($q) use ($orderId) {
            $q->where('order_id', $orderId);
        });
    }

    /**
     * Calculate total price for this modification
     */
    public function calculateTotal(): float
    {
        return $this->quantity * $this->price;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2, ',', '.');
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotal(): string
    {
        return number_format($this->calculateTotal(), 2, ',', '.');
    }

    /**
     * Get formatted quantity
     */
    public function getFormattedQuantity(): string
    {
        return number_format($this->quantity, 0, ',', '.');
    }

    /**
     * Check if modification is available
     */
    public function isAvailable(): bool
    {
        return $this->productModification && $this->productModification->is_available;
    }

    /**
     * Get modification details
     */
    public function getDetails(): array
    {
        return [
            'product_modification_id' => $this->product_modification_id,
            'modification_name' => $this->productModification->name ?? 'Unknown',
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total' => $this->calculateTotal(),
            'formatted_price' => $this->getFormattedPrice(),
            'formatted_total' => $this->getFormattedTotal(),
            'formatted_quantity' => $this->getFormattedQuantity(),
            'is_available' => $this->isAvailable(),
            'notes' => $this->notes,
        ];
    }

    /**
     * Update quantity
     */
    public function updateQuantity(int $quantity): void
    {
        $this->update(['quantity' => $quantity]);
    }

    /**
     * Update price
     */
    public function updatePrice(float $price): void
    {
        $this->update(['price' => $price]);
    }

    /**
     * Update quantity and price
     */
    public function updateQuantityAndPrice(int $quantity, float $price): void
    {
        $this->update([
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    /**
     * Get modification name
     */
    public function getModificationName(): string
    {
        return $this->productModification->name ?? 'Unknown Modification';
    }

    /**
     * Get modification description
     */
    public function getModificationDescription(): string
    {
        return $this->productModification->description ?? '';
    }

    /**
     * Check if this is a custom modification (not linked to product modification)
     */
    public function isCustomModification(): bool
    {
        return $this->product_modification_id === null;
    }

    /**
     * Get modification type
     */
    public function getModificationType(): string
    {
        return $this->productModification->type ?? 'custom';
    }

    /**
     * Get modification category
     */
    public function getModificationCategory(): string
    {
        return $this->productModification->category ?? 'custom';
    }

    /**
     * Check if modification affects price
     */
    public function affectsPrice(): bool
    {
        return $this->price != 0;
    }

    /**
     * Get price impact (positive or negative)
     */
    public function getPriceImpact(): float
    {
        return $this->calculateTotal();
    }

    /**
     * Get formatted price impact
     */
    public function getFormattedPriceImpact(): string
    {
        $total = $this->calculateTotal();
        $sign = $total >= 0 ? '+' : '-';
        return $sign . number_format(abs($total), 2, ',', '.');
    }

    /**
     * Get summary for display
     */
    public function getSummary(): array
    {
        return [
            'name' => $this->getModificationName(),
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total' => $this->calculateTotal(),
            'formatted_price' => $this->getFormattedPrice(),
            'formatted_total' => $this->getFormattedTotal(),
            'price_impact' => $this->getFormattedPriceImpact(),
            'notes' => $this->notes,
        ];
    }

    /**
     * Boot method to set tenant_id from order_item
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($modification) {
            if (empty($modification->tenant_id) && $modification->orderItem) {
                $modification->tenant_id = $modification->orderItem->tenant_id;
            }
        });
    }
}