<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'logo' => null,
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'address' => $this->faker->address(),
            'website' => $this->faker->url(),
            'owner_id' => null,
            'is_active' => true,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => null,
            'subscription_end_date' => null,
            'subscription_status' => 'trial',
            'subdomain' => $this->faker->unique()->word(),
        ];
    }
}