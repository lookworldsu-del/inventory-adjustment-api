<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\AdjustmentReason;
use App\Models\Batch;
use App\Models\InventoryAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentController extends Controller
{
    public function store(
        StoreInventoryAdjustmentRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $adjustment = DB::transaction(function () use ($data): InventoryAdjustment {
            $batch = Batch::query()
                ->whereKey($data['batch_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $reason = AdjustmentReason::query()
                ->whereKey($data['reason_id'])
                ->where('is_active', true)
                ->where('type', 'inventory_adjustment')
                ->sharedLock()
                ->first();

            if ($reason === null) {
                throw ValidationException::withMessages([
                    'reason_id' => 'Please select an active reason that is valid for inventory adjustments.',
                ]);
            }

            $oldQuantity = $batch->quantity;
            $newQuantity = (int) $data['new_quantity'];

            $adjustment = InventoryAdjustment::create([
                'batch_id' => $batch->id,
                'reason_id' => $reason->id,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'quantity_difference' => $newQuantity - $oldQuantity,
                'note' => $data['note'] ?? null,
            ]);

            $batch->quantity = $newQuantity;
            $batch->save();

            return $adjustment;
        });

        $adjustment->load([
            'batch.product',
            'batch.warehouse',
            'reason',
        ]);

        return (new InventoryAdjustmentResource($adjustment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        InventoryAdjustment $inventoryAdjustment
    ): InventoryAdjustmentResource {
        $inventoryAdjustment->load([
            'batch.product',
            'batch.warehouse',
            'reason',
        ]);

        return new InventoryAdjustmentResource($inventoryAdjustment);
    }
}
