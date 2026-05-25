<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'database',
        'is_active',
        'owner_id',
        'settings',
        'subscription_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'subscription_ends_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'uuid');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id', 'id', 'uuid')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }
}
