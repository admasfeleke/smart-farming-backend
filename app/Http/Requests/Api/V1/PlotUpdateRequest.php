<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlotUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plot_name' => ['sometimes', 'string', 'max:100'],
            'area_hectares' => ['nullable', 'numeric'],
            'soil_type' => ['nullable', Rule::in(['clay', 'sandy', 'loam', 'silty', 'peaty', 'chalky', 'unknown'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
