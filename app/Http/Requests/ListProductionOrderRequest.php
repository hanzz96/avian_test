<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'product' => 'nullable|string|max:20',
            'machine' => 'nullable|string|max:10',
            'status' => ['nullable', 'string', 'regex:/^(open|running|finished|cancelled)(,(open|running|finished|cancelled))*$/i'],
            'date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:wo_number,product,machine,operator,shift,status,target_qty,plan_start,plan_finish,good_qty,reject_qty',
            'sort_dir' => 'nullable|in:asc,desc',
        ];
    }
}
