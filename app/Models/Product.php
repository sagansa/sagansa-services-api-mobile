<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'category_id',
        'user_id',
        'name',
        'slug',
        'description',
        'type',
        'bundle_pricing_mode',
        'price',
        'image',
        'sku',
        'barcode',
        'stock',
        'request',
        'remaining',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'request' => 'boolean',
            'remaining' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['category_detail', 'bundle_available_stock', 'image_url'];

    public function toArray()
    {
        $array = parent::toArray();
        
        // Add category as string (name) if relationship is loaded
        // Only override if category relationship is loaded, otherwise keep as-is
        if ($this->relationLoaded('category')) {
            $categoryRelation = $this->getRelation('category');
            // Only set category if not already set (to avoid overriding)
            // Only set category if not already set (to avoid overriding)
            if (!array_key_exists('category', $array) || is_array($array['category']) || is_null($array['category'])) {
                $array['category'] = $categoryRelation ? $categoryRelation->name : null;
            }
        }

        if ($this->relationLoaded('bundleItems')) {
            $array['bundle_items'] = $this->getRelation('bundleItems')
                ->map(function ($item) {
                    $component = $item->componentProduct;

                    return [
                        'id' => $item->id,
                        'bundle_product_id' => $item->bundle_product_id,
                        'component_product_id' => $item->component_product_id,
                        'quantity' => (int) $item->quantity,
                        'sort_order' => (int) $item->sort_order,
                        'component_product' => $component ? [
                            'id' => $component->id,
                            'name' => $component->name,
                            'price' => (int) $component->price,
                            'stock' => (int) $component->stock,
                            'is_active' => (bool) $component->is_active,
                        ] : null,
                    ];
                })
                ->values()
                ->all();
        }
        
        return $array;
    }

    public function getCategoryDetailAttribute()
    {
        // Access the relationship using getRelation to avoid infinite loop
        if ($this->relationLoaded('category')) {
            $categoryRelation = $this->getRelation('category');
            if ($categoryRelation) {
                return [
                    'id' => $categoryRelation->id,
                    'name' => $categoryRelation->name,
                ];
            }
        }
        
        return null;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function getBundleAvailableStockAttribute(): ?int
    {
        if (($this->type ?: 'single') !== 'bundle') {
            return null;
        }

        if (! $this->relationLoaded('bundleItems')) {
            return null;
        }

        if ($this->bundleItems->isEmpty()) {
            return null;
        }

        return $this->bundleItems
            ->map(function ($item) {
                $quantity = max(1, (int) $item->quantity);
                $stock = (int) ($item->componentProduct?->stock ?? 0);

                return intdiv($stock, $quantity);
            })
            ->min();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'product_store')
            ->withPivot('price')
            ->withTimestamps();
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function variantGroups()
    {
        return $this->hasMany(ProductVariantGroup::class)->orderBy('order');
    }

    public function variantCombinations()
    {
        return $this->hasMany(ProductVariantCombination::class);
    }

    public function modifications()
    {
        return $this->hasMany(ProductModification::class);
    }

    public function productPrices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function bundleItems()
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id')->orderBy('sort_order');
    }

    public function includedInBundles()
    {
        return $this->hasMany(ProductBundleItem::class, 'component_product_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHasStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeAvailableInStore($query, $storeId)
    {
        return $query->whereHas('stores', function ($q) use ($storeId) {
            $q->where('stores.id', $storeId);
        });
    }
}
