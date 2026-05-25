<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductStoreAvailability extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_store_availability';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'variant_id',
        'store_id',
        'is_available',
        'stock',
        'min_stock',
        'display_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /**
     * Relationships
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Scopes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForVariant($query, string $variantId)
    {
        return $query->where('variant_id', $variantId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Check if product is available in store
     */
    public function isAvailable(): bool
    {
        return $this->is_available && $this->product->is_available;
    }

    /**
     * Check if product is in stock in store
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if product is low on stock in store
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    /**
     * Decrease stock
     */
    public function decreaseStock(int $quantity): bool
    {
        if ($this->stock < $quantity) {
            return false;
        }
        
        $this->stock -= $quantity;
        return $this->save();
    }

    /**
     * Increase stock
     */
    public function increaseStock(int $quantity): bool
    {
        $this->stock += $quantity;
        return $this->save();
    }
}