<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductModification extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_modifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'type',
        'price',
        'cost_price',
        'is_required',
        'is_multiple',
        'min_selection',
        'max_selection',
        'is_active',
        'display_order',
        'metadata',
        'linked_product_id',
        'linked_product_quantity',
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
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'min_selection' => 'integer',
            'max_selection' => 'integer',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'metadata' => 'array',
            'linked_product_quantity' => 'integer',
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

    public function linkedProduct()
    {
        return $this->belongsTo(Product::class, 'linked_product_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItemModification::class, 'modification_id');
    }

    public function modificationOptions()
    {
        return $this->hasMany(ProductModificationOption::class, 'modification_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }

    public function scopeMultiple($query)
    {
        return $query->where('is_multiple', true);
    }

    public function scopeSingle($query)
    {
        return $query->where('is_multiple', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if modification is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if modification is required
     */
    public function isRequired(): bool
    {
        return $this->is_required;
    }

    /**
     * Check if modification allows multiple selections
     */
    public function isMultiple(): bool
    {
        return $this->is_multiple;
    }

    /**
     * Check if modification allows single selection
     */
    public function isSingle(): bool
    {
        return !$this->is_multiple;
    }

    /**
     * Check if modification has options
     */
    public function hasOptions(): bool
    {
        return $this->modificationOptions()->exists();
    }

    /**
     * Get active options
     */
    public function getActiveOptions()
    {
        return $this->modificationOptions()->active()->get();
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
     * Check if selection count is valid
     */
    public function isValidSelectionCount(int $count): bool
    {
        if ($count < $this->min_selection) {
            return false;
        }

        if ($this->max_selection > 0 && $count > $this->max_selection) {
            return false;
        }

        return true;
    }

    /**
     * Get validation message for selection count
     */
    public function getSelectionValidationMessage(): string
    {
        if ($this->is_multiple) {
            if ($this->min_selection > 0 && $this->max_selection > 0) {
                return "Pilih minimal {$this->min_selection} dan maksimal {$this->max_selection} opsi";
            } elseif ($this->min_selection > 0) {
                return "Pilih minimal {$this->min_selection} opsi";
            } elseif ($this->max_selection > 0) {
                return "Pilih maksimal {$this->max_selection} opsi";
            }
        } else {
            return "Pilih 1 opsi";
        }

        return "";
    }

    /**
     * Get modification type label
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'size' => 'Ukuran',
            'topping' => 'Topping',
            'extra' => 'Extra',
            'addon' => 'Tambahan',
            'flavor' => 'Rasa',
            'customization' => 'Kustomisasi',
            default => ucfirst($this->type),
        };
    }
}
