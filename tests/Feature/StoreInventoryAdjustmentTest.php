<?php

namespace Tests\Feature;

use App\Models\AdjustmentReason;
use App\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreInventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_adjustment_and_updates_only_the_selected_batch(): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $otherBatch = Batch::factory()->create(['quantity' => 50]);
        $reason = AdjustmentReason::factory()->create();

        $response = $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
            'note' => 'Morning stock count: actual quantity is 92.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.batch_id', $batch->id)
            ->assertJsonPath('data.reason_id', $reason->id)
            ->assertJsonPath('data.old_quantity', 100)
            ->assertJsonPath('data.new_quantity', 92)
            ->assertJsonPath('data.quantity_difference', -8)
            ->assertJsonPath('data.note', 'Morning stock count: actual quantity is 92.')
            ->assertJsonPath('data.batch.id', $batch->id)
            ->assertJsonPath('data.batch.quantity', 92)
            ->assertJsonPath('data.batch.product.id', $batch->product_id)
            ->assertJsonPath('data.batch.warehouse.id', $batch->warehouse_id)
            ->assertJsonPath('data.reason.id', $reason->id);

        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $response->json('data.id'),
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'old_quantity' => 100,
            'new_quantity' => 92,
            'quantity_difference' => -8,
            'note' => 'Morning stock count: actual quantity is 92.',
        ]);
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 92]);
        $this->assertDatabaseHas('batches', ['id' => $otherBatch->id, 'quantity' => 50]);
    }

    #[DataProvider('validQuantityChanges')]
    public function test_it_records_valid_quantity_changes(int $oldQuantity, int $newQuantity, int $difference): void
    {
        $batch = Batch::factory()->create(['quantity' => $oldQuantity]);
        $reason = AdjustmentReason::factory()->create();

        $response = $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => $newQuantity,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.old_quantity', $oldQuantity)
            ->assertJsonPath('data.new_quantity', $newQuantity)
            ->assertJsonPath('data.quantity_difference', $difference);

        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $response->json('data.id'),
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'quantity_difference' => $difference,
        ]);
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => $newQuantity]);
    }

    /** @return array<string, array{int, int, int}> */
    public static function validQuantityChanges(): array
    {
        return [
            'increase' => [100, 120, 20],
            'clear stock' => [100, 0, -100],
            'unchanged quantity is recorded' => [100, 100, 0],
            'restock empty batch' => [0, 10, 10],
            'maximum positive difference' => [0, 4294967295, 4294967295],
            'maximum negative difference' => [4294967295, 0, -4294967295],
        ];
    }

    #[DataProvider('validNotes')]
    public function test_it_accepts_optional_notes(array $noteInput, ?string $expectedNote): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();

        $response = $this->postJson(route('inventory-adjustments.store'), array_merge([
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
        ], $noteInput));

        $response->assertCreated()->assertJsonPath('data.note', $expectedNote);
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $response->json('data.id'),
            'note' => $expectedNote,
        ]);
    }

    /** @return array<string, array{array{note?: string|null}, string|null}> */
    public static function validNotes(): array
    {
        return [
            'omitted' => [[], null],
            'null' => [['note' => null], null],
            'empty string' => [['note' => ''], null],
            'surrounding whitespace' => [['note' => '  Manual stock count  '], 'Manual stock count'],
            'maximum unicode length' => [['note' => str_repeat('é', 1000)], str_repeat('é', 1000)],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_it_rejects_invalid_input_without_changing_stock(
        array $overrides,
        array $missingFields,
        string $errorField,
        ?string $expectedMessage = null
    ): void {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();
        $input = array_replace([
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
        ], $overrides);

        foreach ($missingFields as $field) {
            unset($input[$field]);
        }

        $response = $this->postJson(route('inventory-adjustments.store'), $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField]);

        if ($expectedMessage !== null) {
            $response->assertJsonPath('errors.'.$errorField.'.0', $expectedMessage);
        }

        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 100]);
        $this->assertDatabaseEmpty('inventory_adjustments');
    }

    /** @return array<string, array{0: array<string, mixed>, 1: list<string>, 2: string, 3?: string}> */
    public static function invalidInputs(): array
    {
        return [
            'missing batch' => [[], ['batch_id'], 'batch_id'],
            'null batch' => [['batch_id' => null], [], 'batch_id'],
            'nonexistent batch' => [['batch_id' => 99999999], [], 'batch_id', 'The selected batch does not exist.'],
            'non-integer batch' => [['batch_id' => 'invalid'], [], 'batch_id'],
            'array batch' => [['batch_id' => [1]], [], 'batch_id'],
            'missing reason' => [[], ['reason_id'], 'reason_id'],
            'null reason' => [['reason_id' => null], [], 'reason_id'],
            'nonexistent reason' => [['reason_id' => 99999999], [], 'reason_id', 'Please select an active reason that is valid for inventory adjustments.'],
            'non-integer reason' => [['reason_id' => 'invalid'], [], 'reason_id'],
            'array reason' => [['reason_id' => [1]], [], 'reason_id'],
            'free text cannot replace reason' => [['reason' => 'An arbitrary free-text reason'], ['reason_id'], 'reason_id'],
            'missing quantity' => [[], ['new_quantity'], 'new_quantity'],
            'null quantity' => [['new_quantity' => null], [], 'new_quantity'],
            'negative quantity' => [['new_quantity' => -1], [], 'new_quantity', 'The new quantity must be at least 0.'],
            'fractional quantity' => [['new_quantity' => 92.5], [], 'new_quantity', 'The new quantity must be an integer.'],
            'text quantity' => [['new_quantity' => 'many'], [], 'new_quantity', 'The new quantity must be an integer.'],
            'array quantity' => [['new_quantity' => [92]], [], 'new_quantity', 'The new quantity must be an integer.'],
            'quantity overflow' => [['new_quantity' => 4294967296], [], 'new_quantity', 'The new quantity must not exceed 4294967295.'],
            'numeric note' => [['note' => 123], [], 'note'],
            'array note' => [['note' => ['text']], [], 'note'],
            'note too long' => [['note' => str_repeat('é', 1001)], [], 'note', 'The note must not exceed 1000 characters.'],
        ];
    }

    #[DataProvider('unavailableReasons')]
    public function test_it_rejects_unavailable_reasons_without_changing_stock(bool $active, string $type): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create(['is_active' => $active, 'type' => $type]);

        $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_id'])
            ->assertJsonPath('errors.reason_id.0', 'Please select an active reason that is valid for inventory adjustments.');

        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 100]);
        $this->assertDatabaseEmpty('inventory_adjustments');
    }

    /** @return array<string, array{bool, string}> */
    public static function unavailableReasons(): array
    {
        return [
            'inactive inventory reason' => [false, 'inventory_adjustment'],
            'active reason for another purpose' => [true, 'order_cancellation'],
            'inactive reason for another purpose' => [false, 'order_cancellation'],
        ];
    }

    public function test_it_calculates_history_on_the_server_instead_of_trusting_client_fields(): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();

        $response = $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
            'old_quantity' => 999,
            'quantity_difference' => 123,
            'id' => 99999999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.old_quantity', 100)
            ->assertJsonPath('data.quantity_difference', -8);
        $this->assertNotSame(99999999, $response->json('data.id'));
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $response->json('data.id'),
            'old_quantity' => 100,
            'new_quantity' => 92,
            'quantity_difference' => -8,
        ]);
    }

    public function test_successive_adjustments_use_the_latest_quantity_and_preserve_history(): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();
        $input = ['batch_id' => $batch->id, 'reason_id' => $reason->id];

        $first = $this->postJson(route('inventory-adjustments.store'), $input + ['new_quantity' => 92]);
        $first->assertCreated()->assertJsonPath('data.old_quantity', 100);

        $second = $this->postJson(route('inventory-adjustments.store'), $input + ['new_quantity' => 90]);
        $second->assertCreated()
            ->assertJsonPath('data.old_quantity', 92)
            ->assertJsonPath('data.new_quantity', 90)
            ->assertJsonPath('data.quantity_difference', -2);

        $this->assertDatabaseCount('inventory_adjustments', 2);
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $first->json('data.id'), 'old_quantity' => 100, 'new_quantity' => 92, 'quantity_difference' => -8,
        ]);
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $second->json('data.id'), 'old_quantity' => 92, 'new_quantity' => 90, 'quantity_difference' => -2,
        ]);
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 90]);
    }
}
