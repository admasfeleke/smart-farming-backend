<?php

namespace App\Http\Requests\Api\V1;

use App\Support\RegionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region_id' => [
                'sometimes',
                'integer',
                Rule::exists('regions', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = $this->user();
                    if (! $user || empty($user->region_id)) {
                        return;
                    }

                    if (! RegionScope::canAccessRegion($user, (int) $value, true)) {
                        $fail('The selected region is outside your account scope.');
                    }
                },
            ],
            'farm_name' => ['sometimes', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'area_hectares' => ['nullable', 'numeric'],
            'farm_type' => ['nullable', Rule::in(['crop', 'mixed', 'livestock'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
