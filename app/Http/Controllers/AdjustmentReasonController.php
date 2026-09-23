<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdjustmentReasonResource;
use App\Models\AdjustmentReason;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdjustmentReasonController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $reasons = AdjustmentReason::query()
            ->where('is_active', true)
            ->where('type', 'inventory_adjustment')
            ->orderBy('id')
            ->get();

        return AdjustmentReasonResource::collection($reasons);
    }
}
