<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'batch_id' => [
                'bail',
                'required',
                'integer',
                'exists:batches,id',
            ],

            'reason_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('adjustment_reasons', 'id')
                    ->where('is_active', true)
                    ->where('type', 'inventory_adjustment'),
            ],

            'new_quantity' => [
                'bail',
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'note' => [
                'bail',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'batch_id.exists' => '所选批次不存在。',
            'reason_id.exists' => '请选择已启用且适用于库存调整的原因。',
            'new_quantity.integer' => '调整后的数量必须是整数。',
            'new_quantity.min' => '调整后的数量不能小于 0。',
            'new_quantity.max' => '调整后的数量超出允许范围。',
            'note.max' => '备注不能超过 1000 个字符。',
        ];
    }
}
