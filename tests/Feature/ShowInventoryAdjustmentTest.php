<?php

namespace Tests\Feature;

use App\Models\InventoryAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShowInventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_adjustment_with_its_batch_product_warehouse_and_reason(): void
    {
        $adjustment = InventoryAdjustment::factory()->create(['note' => '盘点少了 8 件']);
        $batch = $adjustment->batch;

        $this->getJson(route('inventory-adjustments.show', $adjustment))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $adjustment->id,
                    'batch_id' => $batch->id,
                    'reason_id' => $adjustment->reason_id,
                    'old_quantity' => 100,
                    'new_quantity' => 92,
                    'quantity_difference' => -8,
                    'note' => '盘点少了 8 件',
                    'batch' => [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'quantity' => 92,
                        'product' => [
                            'id' => $batch->product->id,
                            'name' => $batch->product->name,
                            'sku' => $batch->product->sku,
                        ],
                        'warehouse' => [
                            'id' => $batch->warehouse->id,
                            'name' => $batch->warehouse->name,
                        ],
                    ],
                    'reason' => [
                        'id' => $adjustment->reason->id,
                        'name' => $adjustment->reason->name,
                    ],
                ],
            ]);
    }

    public function test_it_returns_404_for_an_unknown_adjustment(): void
    {
        $this->getJson(route('inventory-adjustments.show', 999999))
            ->assertNotFound();
    }

    public function test_historical_quantities_remain_unchanged_after_another_adjustment(): void
    {
        $adjustment = InventoryAdjustment::factory()->create();

        $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $adjustment->batch_id,
            'reason_id' => $adjustment->reason_id,
            'new_quantity' => 80,
        ])->assertCreated();

        $this->getJson(route('inventory-adjustments.show', $adjustment))
            ->assertOk()
            ->assertJsonPath('data.old_quantity', 100)
            ->assertJsonPath('data.new_quantity', 92)
            ->assertJsonPath('data.quantity_difference', -8)
            ->assertJsonPath('data.note', null)
            ->assertJsonPath('data.batch.quantity', 80);

        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $adjustment->id,
            'old_quantity' => 100,
            'new_quantity' => 92,
            'quantity_difference' => -8,
        ]);
    }

    #[DataProvider('unavailableReasonStates')]
    public function test_it_keeps_the_historical_reason_visible_when_it_becomes_unavailable(
        array $reasonChanges
    ): void {
        $adjustment = InventoryAdjustment::factory()->create();
        $adjustment->reason->update($reasonChanges);

        $this->getJson(route('inventory-adjustments.show', $adjustment))
            ->assertOk()
            ->assertJsonPath('data.reason.id', $adjustment->reason_id)
            ->assertJsonPath('data.reason.name', $adjustment->reason->name);
    }

    /**
     * @return array<string, array{array{is_active?: bool, type?: string}}>
     */
    public static function unavailableReasonStates(): array
    {
        return [
            'deactivated reason' => [['is_active' => false]],
            'reason reassigned to orders' => [['type' => 'order_cancellation']],
        ];
    }
}
