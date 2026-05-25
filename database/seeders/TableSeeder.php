<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get all stores
        $stores = Store::all();

        foreach ($stores as $store) {
            // Create 10 tables for each store
            for ($i = 1; $i <= 10; $i++) {
                Table::firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'table_number' => (string)$i,
                    ],
                    [
                        'is_available' => true,
                        'capacity' => rand(2, 6), // Random capacity between 2-6 people
                    ]
                );
            }
        }
    }
}
