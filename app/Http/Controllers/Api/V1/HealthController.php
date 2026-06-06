<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InferencePipelineService;
use App\Support\RegionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function show(Request $request, InferencePipelineService $inference): JsonResponse
    {
        $user = $request->user();
        $inferenceEnabled = (bool) config('services.inference.enabled');
        $inferenceReport = $inference->healthReport();
        $inferenceSummary = [
            'enabled' => $inferenceEnabled,
            'healthy' => (bool) ($inferenceReport['healthy'] ?? true),
            'service_status' => (string) ($inferenceReport['service_status'] ?? 'unknown'),
        ];

        $payload = [
            'status' => 'ok',
            'user_id' => $user->id,
            'services' => [
                'inference' => $inferenceSummary,
            ],
        ];

        if (RegionScope::isBackoffice($user)) {
            $payload['services']['inference'] = [
                ...$inferenceSummary,
                'contract_ok' => (bool) ($inferenceReport['contract_ok'] ?? true),
                'contract_messages' => (array) ($inferenceReport['contract_messages'] ?? []),
                'runtime' => (array) ($inferenceReport['runtime'] ?? []),
                'errors' => (array) ($inferenceReport['errors'] ?? []),
                'base_url' => (string) config('services.inference.base_url', ''),
                'endpoint' => (string) config('services.inference.endpoint', '/predict'),
                'strict_precheck' => (bool) config('services.inference.strict_precheck', false),
                'min_confidence' => (float) config('services.inference.min_confidence', 0.75),
                'review_only_mode' => (bool) config('services.inference.review_only_mode', false),
                'expected_model_version' => (string) config('services.inference.expected_model_version', ''),
                'expected_pixel_scale' => (string) config('services.inference.expected_pixel_scale', ''),
                'expected_labels_count' => (int) config('services.inference.expected_labels_count', 0),
                'enforce_crop_scope' => (bool) config('services.inference.enforce_crop_scope', false),
                'supported_crop_families' => (array) config('services.inference.supported_crop_families', []),
            ];
        }

        return response()->json($payload);
    }
}
