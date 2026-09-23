<?php

namespace App\Models;

use Database\Factories\InventoryAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'reason_id',
    'old_quantity',
    'new_quantity',
    'quantity_difference',
    'note',
])]
class InventoryAdjustment extends Model
{
    /** @use HasFactory<InventoryAdjustmentFactory> */
    use HasFactory;

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AdjustmentReason::class, 'reason_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_quantity' => 'integer',
            'new_quantity' => 'integer',
            'quantity_difference' => 'integer',
        ];
    }
}
