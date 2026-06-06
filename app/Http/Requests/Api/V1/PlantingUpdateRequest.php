<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PlantingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeDateInputs(['planting_date', 'expected_harvest_date']));
    }

    public function rules(): array
    {
        return [
            'crop_id' => ['sometimes', 'integer', Rule::exists('crops', 'id')],
            'planting_date' => ['sometimes', 'date'],
            'expected_harvest_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['planned', 'active', 'harvested', 'failed'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, string|null>
     */
    private function normalizeDateInputs(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            if ($value === null || $value === '') {
                $normalized[$field] = null;
                continue;
            }

            try {
                $normalized[$field] = Carbon::parse((string) $value)->toDateString();
            } catch (\Throwable) {
                // Leave invalid values untouched so normal request validation can reject them.
            }
        }

        return $normalized;
    }
}