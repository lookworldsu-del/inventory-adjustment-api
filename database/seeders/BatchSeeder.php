<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $shanghaiWarehouse = Warehouse::where('name', 'Shanghai Warehouse')->firstOrFail();
        $guangzhouWarehouse = Warehouse::where('name', 'Guangzhou Warehouse')->firstOrFail();

        $mouse = Product::where('sku', 'MOUSE-001')->firstOrFail();
        $keyboard = Product::where('sku', 'KEYBOARD-001')->firstOrFail();

        Batch::firstOrCreate(
            ['batch_number' => 'BATCH-001'],
            [
                'warehouse_id' => $shanghaiWarehouse->id,
                'product_id' => $mouse->id,
                'quantity' => 100,
            ],
        );

        Batch::firstOrCreate(
            ['batch_number' => 'BATCH-002'],
            [
                'warehouse_id' => $guangzhouWarehouse->id,
                'product_id' => $keyboard->id,
                'quantity' => 50,
            ],
        );
    }
}
