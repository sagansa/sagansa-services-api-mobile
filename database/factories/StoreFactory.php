<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->sentence(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'radius' => 100,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
        ];
    }
}