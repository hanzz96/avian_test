<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wo_number' => 'required|string|exists:work_order,wo_number',
            'qty_good' => 'required|integer|min:0',
            'qty_reject' => 'required|integer|min:0',
            'production_date' => 'required|date_format:Y-m-d|before_or_equal:today',

            'actual_start' => 'nullable|date_format:Y-m-d H:i:s',
            'actual_finish' => 'nullable|date_format:Y-m-d H:i:s|after:actual_start',
        ];
    }
}
