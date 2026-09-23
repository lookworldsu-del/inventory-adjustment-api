<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use Illuminate\Database\Seeder;

class AdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Physical count correction',
            'Damaged items',
            'Missing items',
            'Data entry correction',
        ];

        foreach ($names as $name) {
            AdjustmentReason::firstOrCreate(
                [
                    'name' => $name,
                    'type' => 'inventory_adjustment',
                ],
                ['is_active' => true],
            );
        }

        AdjustmentReason::firstOrCreate(
            [
                'name' => 'Retired physical count reason',
                'type' => 'inventory_adjustment',
            ],
            ['is_active' => false],
        );

        AdjustmentReason::firstOrCreate(
            [
                'name' => 'Order cancellation',
                'type' => 'order_cancellation',
            ],
            ['is_active' => true],
        );
    }
}
