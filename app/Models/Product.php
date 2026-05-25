<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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

    protected $appends = ['category_detail'];

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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
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
