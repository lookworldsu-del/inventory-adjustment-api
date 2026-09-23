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
            ['name' => '无线鼠标'],
        );

        Product::firstOrCreate(
            ['sku' => 'KEYBOARD-001'],
            ['name' => '机械键盘'],
        );
    }
}
