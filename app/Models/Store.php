<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Store extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'nickname',

        'email',
        'status',
        'radius',
        'latitude',
        'longitude',
        'tax_rate',
        'tax_name',
        'tax_type',
        'service_charge_type',
        'service_charge_rate',
        'service_charge_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'radius' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'tax_rate' => 'float',
        ];
    }

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_store')
            ->withTimestamps();
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

    /**
     * Geofencing methods
     */
    public function isWithinRadius($latitude, $longitude)
    {
        $distance = $this->calculateDistance($latitude, $longitude);
        return $distance <= $this->radius;
    }

    public function calculateDistance($latitude, $longitude)
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithinRadius($query, $latitude, $longitude, $radius = null)
    {
        $radius = $radius ?? $this->radius ?? 1000;
        
        return $query->whereRaw(
            'ST_Distance_Sphere(point(longitude, latitude), point(?, ?)) <= ?',
            [$longitude, $latitude, $radius]
        );
    }
}
