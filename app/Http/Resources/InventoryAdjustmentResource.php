<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'reason_id' => $this->reason_id,
            'old_quantity' => $this->old_quantity,
            'new_quantity' => $this->new_quantity,
            'quantity_difference' => $this->quantity_difference,
            'note' => $this->note,

            'batch' => $this->whenLoaded('batch', function (): array {
                return [
                    'id' => $this->batch->id,
                    'batch_number' => $this->batch->batch_number,
                    'quantity' => $this->batch->quantity,

                    'product' => [
                        'id' => $this->batch->product->id,
                        'name' => $this->batch->product->name,
                        'sku' => $this->batch->product->sku,
                    ],

                    'warehouse' => [
                        'id' => $this->batch->warehouse->id,
                        'name' => $this->batch->warehouse->name,
                    ],
                ];
            }),

            'reason' => new AdjustmentReasonResource(
                $this->whenLoaded('reason')
            ),
        ];
    }
}
