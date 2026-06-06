<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiseaseReportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plot_id' => ['required', 'integer', Rule::exists('plots', 'id')],
            'crop_id' => ['required', 'integer', Rule::exists('crops', 'id')],
            'planting_id' => ['nullable', 'integer', Rule::exists('plantings', 'id')],
            'client_submission_id' => ['nullable', 'string', 'max:100'],
            'disease_name' => ['required', 'string', 'max:100'],
            'report_source' => ['nullable', 'string', Rule::in(['manual', 'ai'])],
            'description' => ['nullable', 'string'],
            'scan_metadata' => ['nullable', 'array'],
            'scan_metadata.growth_stage' => ['nullable', 'string', 'max:50'],
            'scan_metadata.symptom_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'scan_metadata.recent_rain' => ['nullable', 'boolean'],
            'scan_metadata.field_notes' => ['nullable', 'string', 'max:1000'],
            'scan_metadata.capture_shots' => ['nullable', 'integer', 'min:1', 'max:10'],
            'scan_metadata.capture_protocol' => ['nullable', 'string', 'max:60'],
            'scan_metadata.offline_local_disease_name' => ['nullable', 'string', 'max:160'],
            'scan_metadata.offline_local_disease_key' => ['nullable', 'string', 'max:160'],
            'scan_metadata.offline_local_severity' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'scan_metadata.offline_local_confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'scan_metadata.offline_local_model_id' => ['nullable', 'string', 'max:120'],
            'scan_metadata.offline_local_model' => ['nullable', 'string', 'max:120'],
            'scan_metadata.offline_local_provisional' => ['nullable', 'boolean'],
            'scan_metadata.offline_local_inference' => ['nullable', 'string', 'max:1000'],
            'scan_metadata.offline_local_inference_unavailable' => ['nullable', 'string', 'max:1000'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['nullable', 'string', Rule::in(['new', 'reviewing', 'processing', 'confirmed', 'rejected', 'resolved'])],
            'reported_at' => ['nullable', 'date'],
        ];
    }
}
