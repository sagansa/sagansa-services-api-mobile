<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductVariant extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_variants';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_variant_group_id',
        'name',
        'sku',
        'price',
        'cost_price',
        'stock_quantity',
        'stock_alert_threshold',
        'is_active',
        'is_default',
        'display_order',
        'metadata',
        'available_with_variants',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'stock_alert_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'display_order' => 'integer',
            'metadata' => 'array',
            'available_with_variants' => 'array',
        ];
    }

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function group()
    {
        return $this->belongsTo(ProductVariantGroup::class, 'product_variant_group_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_quantity <= stock_alert_threshold');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeBySku($query, string $sku)
    {
        return $query->where('sku', $sku);
    }

    /**
     * Check if variant is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if variant is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Check if variant is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->stock_alert_threshold;
    }

    /**
     * Check if variant is out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    /**
     * Check if variant is default
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Deduct stock quantity
     */
    public function deductStock(int $quantity): bool
    {
        if ($this->stock_quantity < $quantity) {
            return false;
        }

        $this->decrement('stock_quantity', $quantity);
        
        // Create stock movement record
        $this->stockMovements()->create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'type' => 'sale',
            'quantity' => -$quantity,
            'previous_quantity' => $this->stock_quantity + $quantity,
            'new_quantity' => $this->stock_quantity,
            'reference_type' => 'sale',
            'reference_id' => null,
            'notes' => 'Stock deducted from sale',
        ]);

        return true;
    }

    /**
     * Add stock quantity
     */
    public function addStock(int $quantity, string $referenceType = 'restock', ?string $referenceId = null, ?string $notes = null): void
    {
        $previousQuantity = $this->stock_quantity;
        $this->increment('stock_quantity', $quantity);
        
        // Create stock movement record
        $this->stockMovements()->create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'type' => $referenceType,
            'quantity' => $quantity,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $this->stock_quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes ?? 'Stock added',
        ]);
    }

    /**
     * Calculate profit margin
     */
    public function getProfitMargin(): float
    {
        if ($this->cost_price <= 0) {
            return 0;
        }
        
        return (($this->price - $this->cost_price) / $this->price) * 100;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2, ',', '.');
    }

    /**
     * Get stock value (quantity * cost price)
     */
    public function getStockValue(): float
    {
        return $this->stock_quantity * $this->cost_price;
    }

    /**
     * Get potential revenue (quantity * price)
     */
    public function getPotentialRevenue(): float
    {
        return $this->stock_quantity * $this->price;
    }
}