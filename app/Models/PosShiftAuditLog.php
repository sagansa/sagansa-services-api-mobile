<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosShiftAuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pos_shift_audit_logs';

    protected $fillable = [
        'shift_session_id',
        'action',
        'before_payload',
        'after_payload',
        'reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'before_payload' => 'array',
            'after_payload' => 'array',
        ];
    }
}
