<?php

namespace App\Models;

use Database\Factories\AdjustmentReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'is_active'])]
class AdjustmentReason extends Model
{
    /** @use HasFactory<AdjustmentReasonFactory> */
    use HasFactory;

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'reason_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
