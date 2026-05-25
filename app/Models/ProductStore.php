<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStore extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'store_id',
        'price',
        'is_available',
        'stock_quantity',
    ];

    protected $casts = [
        'id' => 'string',
        'tenant_id' => 'string',
        'product_id' => 'string',
        'store_id' => 'string',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'stock_quantity' => 'integer',
    ];

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scopes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeUnavailable($query)
    {
        return $query->where('is_available', false);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Check if product is available in store
     */
    public function isAvailable()
    {
        return $this->is_available === true;
    }

    /**
     * Make product available
     */
    public function makeAvailable()
    {
        $this->update(['is_available' => true]);
    }

    /**
     * Make product unavailable
     */
    public function makeUnavailable()
    {
        $this->update(['is_available' => false]);
    }

    /**
     * Update stock quantity
     */
    public function updateStock($quantity)
    {
        $this->update(['stock_quantity' => $quantity]);
    }

    /**
     * Increment stock
     */
    public function incrementStock($quantity = 1)
    {
        $this->increment('stock_quantity', $quantity);
    }

    /**
     * Decrement stock
     */
    public function decrementStock($quantity = 1)
    {
        $this->decrement('stock_quantity', $