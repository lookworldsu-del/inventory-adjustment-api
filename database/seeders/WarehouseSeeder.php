<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate([
            'name' => '上海仓库',
        ]);

        Warehouse::firstOrCreate([
            'name' => '广州仓库',
        ]);
    }
}
