<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\TenantScope;

class ProductModificationOption extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_modification_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'modification_id',
        'name',
        'price',
        'cost_price',
        'is_active',
        'display_order',
        'metadata',
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
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function modification()
    {
        return $this->belongsTo(ProductModification::class, 'modification_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItemModification::class, 'option_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByModification($query, string $modificationId)
    {
        return $query->where('modification_id', $modificationId);
    }

    /**
     * Check if option is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
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
     * Get price difference from base modification
     */
    public function getPriceDifference(): float
    {
        return $this->price - $this->modification->price;
    }

    /**
     * Check if this option increases the price
     */
    public function isPriceIncrease(): bool
    {
        return $this->getPriceDifference() > 0;
    }

    /**
     * Check if this option decreases the price
     */
    public function isPriceDecrease(): bool
    {
        return $this->getPriceDifference() < 0;
    }

    /**
     * Get price difference formatted
     */
    public function getFormattedPriceDifference(): string
    {
        $difference = $this->getPriceDifference();
        
        if ($difference > 0) {
            return '+ ' . number_format($difference, 2, ',', '.');
        } elseif ($difference < 0) {
            return '- ' . number_format(abs($difference), 2, ',', '.');
        }
        
        return '0,00';
    }

    /**
     * Get display name with price
     */
    public function getDisplayNameWithPrice(): string
    {
        $priceText = $this->getFormattedPriceDifference();
        
        if ($priceText !== '0,00') {
            return "{$this->name} ({$priceText})";
        }
        
        return $this->name;
    }
}