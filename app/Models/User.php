<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Permission;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, TenantScope;
    use HasRoles {
        getAllPermissions as traitGetAllPermissions;
    }

    protected $connection = 'mysql_auth';

    protected $with = ['detail'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'uuid',
        'tenant_id',
        'role',
        'is_active',
        'manager_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'invitation_token',
        'invitation_expires_at',
        'invited_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'invitation_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    public function detail()
    {
        return $this->hasOne(UserDetail::class, 'id', 'uuid');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Many-to-many relationship for additional tenants user has access to
     */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id', 'uuid', 'id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id', 'uuid');
    }

    public function subordinates()
    {
        return $this->hasManyThrough(User::class, UserDetail::class, 'manager_id', 'uuid', 'uuid', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'created_by', 'uuid');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'created_by_id', 'uuid');
    }

    public function approvedAttendances()
    {
        return $this->hasMany(Attendance::class, 'approved_by_id', 'uuid');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'user_id', 'uuid');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'user_id', 'uuid');
    }

    public function approvedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by', 'uuid');
    }

    public function printers()
    {
        return $this->hasMany(Printer::class, 'user_id', 'uuid');
    }

    /**
     * Many-to-many relationship with stores for cross-tenant access
     */
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'user_stores', 'user_id', 'store_id', 'uuid', 'id');
    }

    public function ownedTenant()
    {
        return $this->hasOne(Tenant::class, 'owner_id', 'uuid');
    }

    /**
     * Override getAllPermissions to give owner all access
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        if ($this->hasRole('owner')) {
            return Permission::all();
        }

        return $this->traitGetAllPermissions();
    }

    // Accessors for backward compatibility
    public function getTenantIdAttribute()
    {
        return $this->detail?->tenant_id;
    }

    public function getRoleAttribute()
    {
        return $this->detail?->role ?? 'staff';
    }

    public function getIsActiveAttribute()
    {
        return (bool) ($this->detail?->is_active ?? true);
    }

    public function getManagerIdAttribute()
    {
        return $this->detail?->manager_id;
    }

    public function getInvitationTokenAttribute()
    {
        return $this->detail?->invitation_token;
    }

    public function getInvitationExpiresAtAttribute()
    {
        return $this->detail?->invitation_expires_at;
    }

    public function getInvitedByAttribute()
    {
        return $this->detail?->invited_by;
    }

    // Mutators for backward compatibility
    public function setTenantIdAttribute($value)
    {
        $this->getOrCreateDetail()->tenant_id = $value;
    }

    public function setRoleAttribute($value)
    {
        $this->getOrCreateDetail()->role = $value;
    }

    public function setIsActiveAttribute($value)
    {
        $this->getOrCreateDetail()->is_active = $value;
    }

    public function setManagerIdAttribute($value)
    {
        $this->getOrCreateDetail()->manager_id = $value;
    }

    public function setInvitationTokenAttribute($value)
    {
        $this->getOrCreateDetail()->invitation_token = $value;
    }

    public function setInvitationExpiresAtAttribute($value)
    {
        $this->getOrCreateDetail()->invitation_expires_at = $value;
    }

    public function setInvitedByAttribute($value)
    {
        $this->getOrCreateDetail()->invited_by = $value;
    }

    protected function getOrCreateDetail()
    {
        if (!$this->relationLoaded('detail')) {
            $this->load('detail');
        }

        $detail = $this->detail;

        if (!$detail) {
            $detail = new UserDetail();
            $detail->id = $this->uuid;
            $this->setRelation('detail', $detail);
        }

        return $detail;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });

        static::saved(function ($user) {
            if ($user->relationLoaded('detail') && $user->detail) {
                $user->detail->id = $user->uuid;
                $user->detail->save();
            }
        });
    }
}
