<?php

namespace App\Jobs;

use App\Models\Crop;
use App\Models\DiseaseReport;
use App\Models\FailedInference;
use App\Services\InferencePipelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessDiseaseReportScan implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $diseaseReportId,
        public readonly string $imagePath,
    ) {
    }

    public function handle(InferencePipelineService $inference): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $startedAt = microtime(true);

        $report = DiseaseReport::find($this->diseaseReportId);
        if (! $report) {
            return;
        }

        try {
            $inferenceEnabled = (bool) config('services.inference.enabled', false);
            $strictInferencePrecheck = (bool) config('services.inference.strict_precheck', false);
            if ($inferenceEnabled && $strictInferencePrecheck && ! $inference->isHealthy()) {
                $report->update([
                    'status' => 'reviewing',
                    'description' => trim(($report->description ?? '').' Inference unavailable at processing time; manual review required.'),
                ]);

                Log::notice('Disease inference skipped at job runtime because service is unhealthy', [
                    'report_id' => $this->diseaseReportId,
                    'crop_id' => (int) $report->crop_id,
                ]);

                return;
            }

            $supportedFamilies = (array) config('services.inference.supported_crop_families', []);
            $enforceCropScope = (bool) config('services.inference.enforce_crop_scope', false);
            $crop = Crop::find((int) $report->crop_id);
            $cropFamily = $this->canonicalFamily($crop?->name);
            $cropFamilySupported = $cropFamily !== null && in_array($cropFamily, $supportedFamilies, true);

            if ($enforceCropScope && $supportedFamilies !== [] && ! $cropFamilySupported) {
                $message = $cropFamily === null
                    ? 'Selected crop family is unknown to AI model; supporter verification required.'
                    : 'Selected crop is not currently supported by AI model; supporter verification required.';
                $report->update([
                    'disease_name' => 'pending_analysis',
                    'severity' => 'low',
                    'confidence_score' => null,
                    'description' => trim(($report->description ?? '').' '.$message),
                    'status' => 'reviewing',
                ]);

                Log::notice('Disease inference skipped for unsupported crop', [
                    'report_id' => $this->diseaseReportId,
                    'crop_id' => (int) $report->crop_id,
                    'crop_name' => (string) ($crop?->name ?? ''),
                    'crop_family' => $cropFamily,
                    'supported_crop_families' => $supportedFamilies,
                    'enforce_crop_scope' => $enforceCropScope,
                ]);

                return;
            }

            $result = $inference->analyze(
                cropId: (int) $report->crop_id,
                imagePath: $this->imagePath,
                cropName: (string) ($crop?->name ?? ''),
            );

            if (($result['ok'] ?? true) === false) {
                $failureCode = strtoupper(trim((string) ($result['code'] ?? 'PIPELINE_REJECTED')));
                $failureMessage = trim((string) ($result['message'] ?? 'Inference pipeline rejected this capture.'));
                $detectedCrop = trim((string) ($result['detected_crop'] ?? ''));
                $selectedCrop = trim((string) ($result['selected_crop'] ?? ($crop?->name ?? '')));
                $gate = is_numeric($result['gate'] ?? null) ? (int) $result['gate'] : null;
                $confidence = is_numeric($result['confidence_score'] ?? null)
                    ? (float) $result['confidence_score']
                    : null;
                $candidateName = trim((string) ($result['candidate_disease_name'] ?? ''));
                $candidateKey = trim((string) ($result['candidate_disease_key'] ?? ''));
                $candidateSeverity = trim((string) ($result['candidate_severity'] ?? ''));
                $candidateConfidence = is_numeric($result['candidate_confidence_score'] ?? null)
                    ? (float) $result['candidate_confidence_score']
                    : null;

                $scanMetadata = is_array($report->scan_metadata) ? $report->scan_metadata : [];
                if ($candidateName !== '') {
                    $scanMetadata['server_inference_disease_name'] = $candidateName;
                    $scanMetadata['server_inference_disease_key'] = $candidateKey !== ''
                        ? $candidateKey
                        : $this->normalizeDiseaseKey($candidateName);
                    $scanMetadata['server_inference_confidence'] = $candidateConfidence;
                    $scanMetadata['server_inference_severity'] = $candidateSeverity !== '' ? $candidateSeverity : null;
                    $scanMetadata['server_inference_status'] = 'candidate_review_required';
                } else {
                    $scanMetadata['server_inference_status'] = 'rejected';
                }
                $scanMetadata['server_inference_rejection_code'] = $failureCode;
                $scanMetadata['server_inference_rejection_message'] = $failureMessage;
                $scanMetadata['server_inference_top_scores'] = $result['top_scores'] ?? [];
                $scanMetadata['server_inference_recorded_at'] = now()->toISOString();

                $report->update([
                    'disease_name' => 'pending_analysis',
                    'severity' => 'low',
                    'confidence_score' => $confidence,
                    'description' => trim(($report->description ?? '').' ['.$failureCode.'] '.$failureMessage),
                    'status' => 'reviewing',
                    'scan_metadata' => $scanMetadata,
                ]);

                if (Schema::hasTable('failed_inferences')) {
                    FailedInference::create([
                        'disease_report_id' => $report->id,
                        'crop_id' => (int) $report->crop_id,
                        'image_path' => $this->imagePath,
                        'gate_code' => $failureCode,
                        'gate_stage' => $gate,
                        'selected_crop' => $selectedCrop !== '' ? $selectedCrop : null,
                        'detected_crop' => $detectedCrop !== '' ? $detectedCrop : null,
                        'confidence_score' => $confidence,
                        'message' => $failureMessage,
                        'model_version' => trim((string) ($result['model_version'] ?? '')) ?: null,
                        'payload' => $this->sanitizeFailurePayload($result),
                        'occurred_at' => now(),
                    ]);
                }

                Log::notice('Disease inference rejected by pipeline gate', [
                    'report_id' => $this->diseaseReportId,
                    'gate_code' => $failureCode,
                    'gate_stage' => $gate,
                    'selected_crop' => $selectedCrop,
                    'detected_crop' => $detectedCrop,
                    'confidence_score' => $confidence,
                    'model_version' => (string) ($result['model_version'] ?? 'unknown'),
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return;
            }

            $confidence = is_numeric($result['confidence_score'] ?? null)
                ? (float) $result['confidence_score']
                : null;
            $minConfidence = (float) config('services.inference.min_confidence', 0.75);
            $reviewOnlyMode = (bool) config('services.inference.review_only_mode', false);
            $predictedFamily = $this->predictedFamily((string) ($result['disease_name'] ?? ''));
            $familyMismatch = $cropFamily !== null
                && $predictedFamily !== null
                && $predictedFamily !== $cropFamily;
            $isUncertain = $reviewOnlyMode || $confidence === null || $confidence < $minConfidence;
            if ($familyMismatch) {
                $isUncertain = true;
            }

            $description = trim((string) ($result['description'] ?? ''));
            if ($reviewOnlyMode) {
                $description = trim($description.' Model is in review-only mode; supporter confirmation required.');
            }
            if ($confidence === null || $confidence < $minConfidence) {
                $description = trim($description.' Low confidence prediction; marked uncertain for supporter verification.');
            }
            if ($familyMismatch) {
                $description = trim($description.' Predicted crop family does not match selected crop; marked uncertain for supporter verification.');
            }
            if ($isUncertain) {
                Log::notice('Disease inference marked uncertain', [
                    'report_id' => $this->diseaseReportId,
                    'confidence_score' => $confidence,
                    'min_confidence' => $minConfidence,
                    'review_only_mode' => $reviewOnlyMode,
                    'family_mismatch' => $familyMismatch,
                    'crop_family' => $cropFamily,
                    'predicted_family' => $predictedFamily,
                ]);
            }

            // Keep report in reviewing state until supporter verifies/approves.
            $scanMetadata = is_array($report->scan_metadata) ? $report->scan_metadata : [];
            $scanMetadata['server_inference_disease_name'] = (string) ($result['disease_name'] ?? '');
            $scanMetadata['server_inference_disease_key'] = $this->normalizeDiseaseKey(
                (string) ($result['disease_name'] ?? '')
            );
            $scanMetadata['server_inference_confidence'] = $confidence;
            $scanMetadata['server_inference_severity'] = (string) ($result['severity'] ?? '');
            $scanMetadata['server_inference_is_healthy'] = $result['is_healthy'] ?? null;
            $scanMetadata['server_inference_top_scores'] = $result['top_scores'] ?? [];
            $scanMetadata['server_inference_status'] = $isUncertain ? 'uncertain' : 'ready';
            $scanMetadata['server_inference_recorded_at'] = now()->toISOString();
            $report->update([
                'disease_name' => $result['disease_name'],
                'severity' => $result['severity'],
                'confidence_score' => $confidence,
                'description' => $description,
                'status' => 'reviewing',
                'scan_metadata' => $scanMetadata,
            ]);

            Log::info('Disease inference completed', [
                'report_id' => $this->diseaseReportId,
                'crop_id' => (int) $report->crop_id,
                'severity' => (string) ($result['severity'] ?? ''),
                'confidence_score' => $confidence,
                'uncertain' => $isUncertain,
                'min_confidence' => $minConfidence,
                'model_version' => (string) ($result['model_version'] ?? 'unknown'),
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'review_only_mode' => (bool) config('services.inference.review_only_mode', false),
                'family_mismatch' => $familyMismatch,
                'crop_family' => $cropFamily,
                'predicted_family' => $predictedFamily,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Disease inference failed', [
                'report_id' => $this->diseaseReportId,
                'error' => $e->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'review_only_mode' => (bool) config('services.inference.review_only_mode', false),
            ]);

            $report->update([
                'status' => 'reviewing',
                'description' => trim(($report->description ?? '').' Inference failed; manual review required.'),
            ]);
        }
    }

    private function canonicalFamily(?string $cropName): ?string
    {
        return $this->resolveFamilyFromText($cropName);
    }

    private function predictedFamily(string $diseaseName): ?string
    {
        $name = trim(strtolower($diseaseName));
        if ($name === '' || $name === 'pending_analysis') {
            return null;
        }

        return $this->resolveFamilyFromText($name);
    }

    private function normalizeDiseaseKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = (string) preg_replace('/[\s\-\/()]+/', '_', $normalized);
        $normalized = (string) preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        $normalized = trim((string) preg_replace('/_+/', '_', $normalized), '_');

        if ($normalized === '') {
            return 'pending_analysis';
        }

        if ($normalized === 'maize_healthy' || $normalized === 'corn_healthy') {
            return 'corn_healthy';
        }

        if (str_starts_with($normalized, 'maize_')) {
            $normalized = 'corn_'.substr($normalized, strlen('maize_'));
        }

        if (str_starts_with($normalized, 'corn_maize_')) {
            $normalized = 'corn_'.substr($normalized, strlen('corn_maize_'));
        }

        return $normalized;
    }

    private function resolveFamilyFromText(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = (string) strtolower($value);
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));

        if ($normalized === '') {
            return null;
        }

        $aliases = [
            'apple' => ['apple'],
            'blueberry' => ['blueberry', 'blue berry'],
            'cherry' => ['cherry'],
            'corn' => ['corn', 'maize'],
            'grape' => ['grape'],
            'orange' => ['orange'],
            'peach' => ['peach'],
            'pepper' => ['pepper', 'bell pepper', 'capsicum'],
            'potato' => ['potato'],
            'raspberry' => ['raspberry'],
            'soybean' => ['soybean', 'soy bean'],
            'squash' => ['squash'],
            'strawberry' => ['strawberry'],
            'tomato' => ['tomato'],
        ];

        foreach ($aliases as $family => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains(' '.$normalized.' ', ' '.$pattern.' ')) {
                    return $family;
                }
            }
        }

        $parts = explode(' ', $normalized);
        $first = $parts[0] ?? null;
        if ($first === null || $first === '') {
            return null;
        }
        if ($first === 'maize') {
            return 'corn';
        }

        return $first;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeFailurePayload(array $payload): array
    {
        $sanitized = Arr::except($payload, [
            'image',
            'image_base64',
            'image_bytes',
            'device_id',
            'device_identifier',
            'gps',
            'location',
            'coordinates',
        ]);

        $limited = [];
        foreach ($sanitized as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 1000) {
                $limited[$key] = mb_substr($value, 0, 1000).'...[truncated]';
                continue;
            }
            $limited[$key] = $value;
        }

        return $limited;
    }
}
