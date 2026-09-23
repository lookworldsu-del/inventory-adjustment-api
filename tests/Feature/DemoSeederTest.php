<?php

namespace Tests\Feature;

use App\Models\AdjustmentReason;
use App\Models\Batch;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_complete_demo_data_with_valid_relationships(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDemoCounts();
        $this->assertDatabaseCount('inventory_adjustments', 0);

        $mouseBatch = Batch::query()->where('batch_number', 'BATCH-001')->firstOrFail();
        $keyboardBatch = Batch::query()->where('batch_number', 'BATCH-002')->firstOrFail();

        $this->assertSame(100, $mouseBatch->quantity);
        $this->assertSame('Shanghai Warehouse', $mouseBatch->warehouse->name);
        $this->assertSame('MOUSE-001', $mouseBatch->product->sku);
        $this->assertSame('Wireless Mouse', $mouseBatch->product->name);
        $this->assertSame(50, $keyboardBatch->quantity);
        $this->assertSame('Guangzhou Warehouse', $keyboardBatch->warehouse->name);
        $this->assertSame('KEYBOARD-001', $keyboardBatch->product->sku);
        $this->assertSame('Mechanical Keyboard', $keyboardBatch->product->name);

        $this->getJson(route('adjustment-reasons.index'))
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.*.name', [
                'Physical count correction',
                'Damaged items',
                'Missing items',
                'Data entry correction',
            ]);

        $this->assertDatabaseHas('adjustment_reasons', [
            'name' => 'Retired physical count reason',
            'type' => 'inventory_adjustment',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('adjustment_reasons', [
            'name' => 'Order cancellation',
            'type' => 'order_cancellation',
            'is_active' => true,
        ]);
    }

    public function test_reseeding_does_not_duplicate_data_or_reset_adjusted_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $batch = Batch::query()->where('batch_number', 'BATCH-001')->firstOrFail();
        $reason = AdjustmentReason::query()->where('name', 'Physical count correction')->firstOrFail();

        $response = $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
        ])->assertCreated();

        $reason->update(['is_active' => false]);
        $this->seed(DatabaseSeeder::class);

        $this->assertDemoCounts();
        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 92]);
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $response->json('data.id'),
            'batch_id' => $batch->id,
            'old_quantity' => 100,
            'new_quantity' => 92,
            'quantity_difference' => -8,
        ]);
        $this->assertFalse($reason->fresh()->is_active);
    }

    private function assertDemoCounts(): void
    {
        $this->assertDatabaseCount('warehouses', 2);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('batches', 2);
        $this->assertDatabaseCount('adjustment_reasons', 6);
    }
}
