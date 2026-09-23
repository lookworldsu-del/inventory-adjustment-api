<?php

namespace Tests\Feature;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\AdjustmentReason;
use App\Models\Batch;
use App\Models\InventoryAdjustment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InventoryAdjustmentTransactionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('writeFailurePoints')]
    public function test_it_rolls_back_all_writes_when_saving_fails(string $eventName, bool $stockWasWritten): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();
        $originalDispatcher = Model::getEventDispatcher();
        $dispatcher = clone $originalDispatcher;
        $eventReached = false;
        Model::setEventDispatcher($dispatcher);

        $dispatcher->listen($eventName, function () use ($batch, $stockWasWritten, &$eventReached): void {
            $eventReached = true;
            $this->assertDatabaseCount('inventory_adjustments', 1);
            $this->assertDatabaseHas('batches', [
                'id' => $batch->id,
                'quantity' => $stockWasWritten ? 92 : 100,
            ]);

            throw new RuntimeException('Simulated failure after a database write.');
        });

        try {
            try {
                $this->withoutExceptionHandling()->postJson(route('inventory-adjustments.store'), [
                    'batch_id' => $batch->id,
                    'reason_id' => $reason->id,
                    'new_quantity' => 92,
                ]);
                $this->fail('The simulated write failure must escape the transaction.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Simulated failure after a database write.', $exception->getMessage());
            }
        } finally {
            Model::setEventDispatcher($originalDispatcher);
        }

        $this->assertTrue($eventReached);
        $this->assertDatabaseEmpty('inventory_adjustments');
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 100]);
    }

    /** @return array<string, array{string, bool}> */
    public static function writeFailurePoints(): array
    {
        return [
            'after inserting the adjustment' => ['eloquent.created: '.InventoryAdjustment::class, false],
            'after updating the batch' => ['eloquent.updated: '.Batch::class, true],
        ];
    }

    #[DataProvider('reasonChangesAfterValidation')]
    public function test_it_rechecks_reason_availability_after_request_validation(string $change): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();

        $this->afterSuccessfulValidation(function () use ($reason, $change): void {
            match ($change) {
                'deactivate' => $reason->update(['is_active' => false]),
                'change purpose' => $reason->update(['type' => 'order_cancellation']),
                'delete' => $reason->delete(),
            };
        });

        $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_id'])
            ->assertJsonPath('errors.reason_id.0', 'Please select an active reason that is valid for inventory adjustments.');

        $this->assertDatabaseEmpty('inventory_adjustments');
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'quantity' => 100]);

        match ($change) {
            'deactivate' => $this->assertDatabaseHas('adjustment_reasons', ['id' => $reason->id, 'is_active' => false]),
            'change purpose' => $this->assertDatabaseHas('adjustment_reasons', ['id' => $reason->id, 'type' => 'order_cancellation']),
            'delete' => $this->assertDatabaseMissing('adjustment_reasons', ['id' => $reason->id]),
        };
    }

    /** @return array<string, array{string}> */
    public static function reasonChangesAfterValidation(): array
    {
        return [
            'deactivated after validation' => ['deactivate'],
            'different purpose after validation' => ['change purpose'],
            'deleted after validation' => ['delete'],
        ];
    }

    public function test_it_returns_404_if_the_batch_disappears_after_request_validation(): void
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $reason = AdjustmentReason::factory()->create();
        $this->afterSuccessfulValidation(function () use ($batch): void {
            $batch->delete();
        });

        $this->postJson(route('inventory-adjustments.store'), [
            'batch_id' => $batch->id,
            'reason_id' => $reason->id,
            'new_quantity' => 92,
        ])->assertNotFound();

        $this->assertDatabaseEmpty('inventory_adjustments');
        $this->assertDatabaseMissing('batches', ['id' => $batch->id]);
    }

    private function afterSuccessfulValidation(Closure $callback): void
    {
        $this->app->bind(StoreInventoryAdjustmentRequest::class, function () use ($callback): StoreInventoryAdjustmentRequest {
            return new class($callback) extends StoreInventoryAdjustmentRequest
            {
                public function __construct(private Closure $callback)
                {
                    parent::__construct();
                }

                protected function passedValidation(): void
                {
                    ($this->callback)();
                }
            };
        });
    }
}
