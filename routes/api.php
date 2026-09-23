<?php

use App\Http\Controllers\AdjustmentReasonController;
use App\Http\Controllers\InventoryAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::get('/adjustment-reasons', [
    AdjustmentReasonController::class,
    'index',
])->name('adjustment-reasons.index');

Route::post('/inventory-adjustments', [
    InventoryAdjustmentController::class,
    'store',
])->name('inventory-adjustments.store');

Route::get('/inventory-adjustments/{inventoryAdjustment}', [
    InventoryAdjustmentController::class,
    'show',
])->name('inventory-adjustments.show');
