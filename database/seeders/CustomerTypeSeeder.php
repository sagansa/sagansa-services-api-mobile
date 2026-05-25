<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use App\Models\Store;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get all stores
        $stores = Store::all();

        $defaultTypes = [
            ['name' => 'Walk-in', 'order' => 1],
            ['name' => 'Gojek', 'order' => 2],
            ['name' => 'Grab', 'order' => 3],
            ['name' => 'Shopee', 'order' => 4],
        ];

        foreach ($stores as $store) {
            foreach ($defaultTypes as $type) {
                CustomerType::firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'name' => $type['name'],
                    ],
                    [
                        'order' => $type['order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
