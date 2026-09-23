<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_number' => fake()->unique()->bothify('BATCH-########-????'),
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'quantity' => 100,
        ];
    }
}
