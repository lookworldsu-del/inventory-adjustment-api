<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::firstOrCreate(
            ['sku' => 'MOUSE-001'],
            ['name' => 'Wireless Mouse'],
        );

        Product::firstOrCreate(
            ['sku' => 'KEYBOARD-001'],
            ['name' => 'Mechanical Keyboard'],
        );
    }
}
