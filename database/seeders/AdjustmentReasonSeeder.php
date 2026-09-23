<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use Illuminate\Database\Seeder;

class AdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            '实物盘点纠正',
            '商品损坏',
            '商品遗失',
            '数据录入纠正',
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
                'name' => '已停用的盘点原因',
                'type' => 'inventory_adjustment',
            ],
            ['is_active' => false],
        );

        AdjustmentReason::firstOrCreate(
            [
                'name' => '订单取消',
                'type' => 'order_cancellation',
            ],
            ['is_active' => true],
        );
    }
}
