<?php

namespace Database\Factories;

use App\Models\AdjustmentReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdjustmentReason>
 */
class AdjustmentReasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => 'inventory_adjustment',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function forOrders(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'order_cancellation',
        ]);
    }
}
