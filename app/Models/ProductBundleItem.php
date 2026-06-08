<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBundleItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'bundle_product_id',
        'component_product_id',
        'quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function bundleProduct()
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function componentProduct()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
