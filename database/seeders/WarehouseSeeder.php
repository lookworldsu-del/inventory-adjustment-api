<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate([
            'name' => 'Shanghai Warehouse',
        ]);

        Warehouse::firstOrCreate([
            'name' => 'Guangzhou Warehouse',
        ]);
    }
}
