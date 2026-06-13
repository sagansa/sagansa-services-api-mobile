<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosShiftStockMovement extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_OPENING = 'opening';
    public const TYPE_SALE = 'sale';
    public const TYPE_ADDITION = 'addition';
    public const TYPE_CLOSING = 'closing';
    public const TYPE_CORRECTION = 'correction';

    protected $table = 'pos_shift_stock_movements';

    protected $fillable = [
        'shift_session_id',
        'product_id',
        'order_id',
        'order_item_id',
        'type',
        'quantity',
        'note',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function shiftSession()
    {
        return $this->belongsTo(PosShiftSession::class, 'shift_session_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
