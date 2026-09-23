<?php

namespace Database\Factories;

use App\Models\AdjustmentReason;
use App\Models\Batch;
use App\Models\InventoryAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAdjustment>
 */
class InventoryAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'old_quantity' => 100,
            'new_quantity' => 92,
            'quantity_difference' => fn (array $attributes): int => $attributes['new_quantity'] - $attributes['old_quantity'],
            'batch_id' => fn (array $attributes): Batch => Batch::factory()->create([
                'quantity' => $attributes['new_quantity'],
            ]),
            'reason_id' => AdjustmentReason::factory(),
            'note' => null,
        ];
    }
}
