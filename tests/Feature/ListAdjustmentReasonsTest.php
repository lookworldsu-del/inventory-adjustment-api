<?php

namespace Tests\Feature;

use App\Models\AdjustmentReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListAdjustmentReasonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_active_inventory_reasons_in_id_order(): void
    {
        $first = AdjustmentReason::factory()->create(['name' => 'Z Physical count correction']);
        AdjustmentReason::factory()->inactive()->create();
        AdjustmentReason::factory()->forOrders()->create();
        AdjustmentReason::factory()->inactive()->forOrders()->create();
        $second = AdjustmentReason::factory()->create(['name' => 'A Damaged items']);

        $this->getJson(route('adjustment-reasons.index'))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['id' => $first->id, 'name' => $first->name],
                    ['id' => $second->id, 'name' => $second->name],
                ],
            ]);
    }

    public function test_it_returns_an_empty_list_when_no_reasons_are_available(): void
    {
        AdjustmentReason::factory()->inactive()->create();
        AdjustmentReason::factory()->forOrders()->create();

        $this->getJson(route('adjustment-reasons.index'))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
