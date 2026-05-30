<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\TenantScope;

class ProductPhoto extends Model
{
    use HasFactory, SoftDeletes, HasUuids, TenantScope;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_photos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'store_id',
        'photo_url',
        'photo_path',
        'alt_text',
        'is_primary',
        'display_order',
        'uploaded_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'display_order' => 'integer',
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

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'uuid');
    }

    /**
     * Scopes
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('created_at');
    }

    public function scopeForProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Get full photo URL
     */
    public function getFullPhotoUrl(): string
    {
        if (filter_var($this->photo_url, FILTER_VALIDATE_URL)) {
            return $this->photo_url;
        }
        
        return asset('storage/' . ltrim($this->photo_path, '/'));
    }

    /**
     * Check if photo is primary
     */
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    /**
     * Set as primary photo
     */
    public function setAsPrimary(): void
    {
        // Remove primary status from other photos of the same product
        $this->product->photos()->where('id', '!=', $this->id)->update(['is_primary' => false]);
        
        // Set this photo as primary
        $this->update(['is_primary' => true]);
    }
}
