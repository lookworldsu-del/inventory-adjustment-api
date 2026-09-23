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
            'batch_id.exists' => 'The selected batch does not exist.',
            'reason_id.exists' => 'Please select an active reason that is valid for inventory adjustments.',
            'new_quantity.integer' => 'The new quantity must be an integer.',
            'new_quantity.min' => 'The new quantity must be at least 0.',
            'new_quantity.max' => 'The new quantity must not exceed 4294967295.',
            'note.max' => 'The note must not exceed 1000 characters.',
        ];
    }
}
