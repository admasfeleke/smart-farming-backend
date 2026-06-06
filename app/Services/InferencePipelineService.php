<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InferencePipelineService
{
    public function isHealthy(): bool
    {
        return (bool) ($this->healthReport()['healthy'] ?? false);
    }

    /**
     * @return array{
     *   enabled:bool,
     *   healthy:bool,
     *   service_status:string,
     *   contract_ok:bool,
     *   contract_messages:array<int, string>,
     *   runtime:array<string,mixed>,
     *   errors:array<int, string>
     * }
     */
    public function healthReport(): array
    {
        $enabled = (bool) config('services.inference.enabled');
        if (! $enabled) {
            return [
                'enabled' => false,
                'healthy' => true,
                'service_status' => 'disabled',
                'contract_ok' => true,
                'contract_messages' => [],
                'runtime' => [],
                'errors' => [],
            ];
        }

        try {
            $payload = $this->fetchHealthPayload();
            $serviceStatus = strtolower((string) ($payload['status'] ?? 'ok'));
            $serviceOk = $payload === [] || $serviceStatus === 'ok';
            $contractMessages = $this->contractValidationMessages($payload);
            $contractOk = $contractMessages === [];

            return [
                'enabled' => true,
                'healthy' => $serviceOk && $contractOk,
                'service_status' => $serviceStatus,
                'contract_ok' => $contractOk,
                'contract_messages' => $contractMessages,
                'runtime' => $payload,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'enabled' => true,
                'healthy' => false,
                'service_status' => 'unreachable',
                'contract_ok' => false,
                'contract_messages' => [],
                'runtime' => [],
                'errors' => [trim($e->getMessage())],
            ];
        }
    }

    /**
     * @return array{
     *   ok:bool,
     *   gate:int|null,
     *   code:string|null,
     *   message:string|null,
     *   selected_crop:string|null,
     *   detected_crop:string|null,
     *   disease_name:string,
     *   severity:string,
     *   confidence_score:float|null,
     *   confidence_flag:string,
     *   description:string,
     *   model_version:string,
     *   disease_key:string|null,
     *   display_disease_name:string|null,
     *   is_healthy:bool|null,
     *   top_scores:array<int,array<string,mixed>>,
     *   candidate_disease_name:string|null,
     *   candidate_disease_key:string|null,
     *   candidate_display_disease_name:string|null,
     *   candidate_severity:string|null,
     *   candidate_confidence_score:float|null,
     *   candidate_is_healthy:bool|null
     * }
     */
    public function analyze(int $cropId, string $cropName, string $imagePath): array
    {
        if (config('services.inference.enabled')) {
            return $this->analyzeWithRemoteModel($cropId, $cropName, $imagePath);
        }

        return $this->fallbackAnalysis($cropId, $cropName, $imagePath);
    }

    /**
     * @return array{
     *   ok:bool,
     *   gate:int|null,
     *   code:string|null,
     *   message:string|null,
     *   selected_crop:string|null,
     *   detected_crop:string|null,
     *   disease_name:string,
     *   severity:string,
     *   confidence_score:float|null,
     *   confidence_flag:string,
     *   description:string,
     *   model_version:string
     * }
     */
    private function fallbackAnalysis(int $cropId, string $cropName, string $imagePath): array
    {
        $selectedCrop = trim($cropName) !== '' ? trim($cropName) : null;

        return [
            'ok' => false,
            'gate' => 0,
            'code' => 'INFERENCE_UNAVAILABLE',
            'message' => 'Remote inference is disabled; manual review required.',
            'selected_crop' => $selectedCrop,
            'detected_crop' => $selectedCrop,
            'disease_name' => 'pending_analysis',
            'severity' => 'low',
            'confidence_score' => null,
            'confidence_flag' => $this->confidenceFlag(null),
            'description' => sprintf(
                'Remote inference is disabled for crop_id %d. Manual review required.',
                $cropId
            ),
            'model_version' => 'fallback-review-only-v2',
            'disease_key' => null,
            'display_disease_name' => null,
            'is_healthy' => null,
            'top_scores' => [],
            'candidate_disease_name' => null,
            'candidate_disease_key' => null,
            'candidate_display_disease_name' => null,
            'candidate_severity' => null,
            'candidate_confidence_score' => null,
            'candidate_is_healthy' => null,
        ];
    }

    /**
     * @return array{
     *   ok:bool,
     *   gate:int|null,
     *   code:string|null,
     *   message:string|null,
     *   selected_crop:string|null,
     *   detected_crop:string|null,
     *   disease_name:string,
     *   severity:string,
     *   confidence_score:float|null,
     *   confidence_flag:string,
     *   description:string,
     *   model_version:string
     * }
     */
    private function analyzeWithRemoteModel(int $cropId, string $cropName, string $imagePath): array
    {
        $baseUrl = rtrim((string) config('services.inference.base_url'), '/');
        $endpoint = ltrim((string) config('services.inference.endpoint'), '/');
        $timeout = (int) config('services.inference.timeout_seconds', 15);
        $retryAttempts = max(1, (int) config('services.inference.retry_attempts', 2));
        $retrySleepMs = max(0, (int) config('services.inference.retry_sleep_ms', 500));
        $token = (string) config('services.inference.token', '');

        if ($baseUrl === '') {
            throw new RuntimeException('Inference service base URL is missing.');
        }

        $absoluteImagePath = Storage::disk('public')->path($imagePath);
        if (! is_file($absoluteImagePath)) {
            throw new RuntimeException('Inference image file is missing.');
        }

        $response = null;
        $lastException = null;
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                $request = Http::timeout(max(5, $timeout))
                    ->connectTimeout(max(2, min($timeout, 8)))
                    ->acceptJson();

                if ($token !== '') {
                    $request = $request->withToken($token);
                }

                $response = $request
                    ->attach('image', file_get_contents($absoluteImagePath), basename($absoluteImagePath))
                    ->post($baseUrl.'/'.$endpoint, [
                        'crop_id' => $cropId,
                        'selected_crop' => trim($cropName) !== '' ? trim($cropName) : null,
                    ]);

                if (
                    $response->successful() ||
                    $attempt >= $retryAttempts ||
                    ! $this->isRetriableStatus($response->status())
                ) {
                    break;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt >= $retryAttempts) {
                    throw $e;
                }
            }

            if ($retrySleepMs > 0) {
                usleep($retrySleepMs * 1000);
            }
        }

        if ($response === null) {
            if ($lastException instanceof \Throwable) {
                throw $lastException;
            }
            throw new RuntimeException('Inference service request failed before response.');
        }

        if (! $response->successful()) {
            $details = trim((string) $response->body());
            if ($details !== '') {
                throw new RuntimeException('Inference service returned '.$response->status().': '.$details);
            }
            throw new RuntimeException('Inference service returned '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid inference response format.');
        }

        return $this->normalizeRemotePayload($payload, $cropName);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   ok:bool,
     *   gate:int|null,
     *   code:string|null,
     *   message:string|null,
     *   selected_crop:string|null,
     *   detected_crop:string|null,
     *   disease_name:string,
     *   severity:string,
     *   confidence_score:float|null,
     *   confidence_flag:string,
     *   description:string,
     *   model_version:string
     * }
     */
    private function normalizeRemotePayload(array $payload, string $cropName): array
    {
        $selectedCrop = trim($cropName) !== ''
            ? trim($cropName)
            : $this->nullableTrim($payload['selected'] ?? $payload['selected_crop'] ?? null);
        $modelVersion = $this->nullableTrim($payload['model_version'] ?? null) ?? 'remote-unknown';

        if (($payload['ok'] ?? null) === false) {
            $code = strtoupper($this->nullableTrim($payload['code'] ?? null) ?? 'PIPELINE_REJECTED');
            $message = $this->nullableTrim($payload['message'] ?? null) ?? 'Inference pipeline rejected this capture.';
            $gate = $this->asIntOrNull($payload['gate'] ?? null);
            $detectedCrop = $this->nullableTrim($payload['detected'] ?? $payload['detected_crop'] ?? null);
            $confidence = $this->asFloatOrNull(
                $payload['confidence_score']
                    ?? $payload['leaf_confidence']
                    ?? $payload['crop_confidence']
                    ?? null
            );
            $candidateConfidence = $this->asFloatOrNull($payload['candidate_confidence_score'] ?? null);

            return [
                'ok' => false,
                'gate' => $gate,
                'code' => $code,
                'message' => $message,
                'selected_crop' => $selectedCrop,
                'detected_crop' => $detectedCrop,
                'disease_name' => 'pending_analysis',
                'severity' => 'low',
                'confidence_score' => $confidence,
                'confidence_flag' => $this->confidenceFlag($confidence),
                'description' => $message,
                'model_version' => $modelVersion,
                'disease_key' => null,
                'display_disease_name' => null,
                'is_healthy' => null,
                'top_scores' => $this->sanitizeTopScores($payload['top_scores'] ?? []),
                'candidate_disease_name' => $this->nullableTrim($payload['candidate_disease_name'] ?? null),
                'candidate_disease_key' => $this->nullableTrim($payload['candidate_disease_key'] ?? null),
                'candidate_display_disease_name' => $this->nullableTrim($payload['candidate_display_disease_name'] ?? null),
                'candidate_severity' => $this->validSeverityOrNull($payload['candidate_severity'] ?? null),
                'candidate_confidence_score' => $candidateConfidence === null
                    ? null
                    : max(0.0, min(1.0, $candidateConfidence)),
                'candidate_is_healthy' => is_bool($payload['candidate_is_healthy'] ?? null)
                    ? $payload['candidate_is_healthy']
                    : null,
            ];
        }

        $diseaseName = $this->nullableTrim($payload['disease_name'] ?? null);
        $severity = $this->nullableTrim($payload['severity'] ?? null);
        $confidence = $this->asFloatOrNull($payload['confidence_score'] ?? null);
        $description = $this->nullableTrim($payload['description'] ?? null) ?? '';

        if ($diseaseName === null || $severity === null) {
            throw new RuntimeException('Inference response missing required fields.');
        }
        if (! in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new RuntimeException('Inference severity value is invalid.');
        }
        if ($confidence === null) {
            throw new RuntimeException('Inference confidence score is invalid.');
        }

        $confidence = max(0.0, min(1.0, $confidence));
        $confidenceFlagRaw = strtolower((string) ($payload['confidence_flag'] ?? ''));
        $confidenceFlag = in_array($confidenceFlagRaw, ['high', 'low'], true)
            ? $confidenceFlagRaw
            : $this->confidenceFlag($confidence);

        return [
            'ok' => true,
            'gate' => 3,
            'code' => null,
            'message' => null,
            'selected_crop' => $selectedCrop,
            'detected_crop' => $this->nullableTrim($payload['detected'] ?? $payload['detected_crop'] ?? null),
            'disease_name' => $diseaseName,
            'severity' => $severity,
            'confidence_score' => round($confidence, 4),
            'confidence_flag' => $confidenceFlag,
            'description' => $description,
            'model_version' => $modelVersion,
            'disease_key' => $this->nullableTrim($payload['disease_key'] ?? null),
            'display_disease_name' => $this->nullableTrim($payload['display_disease_name'] ?? null),
            'is_healthy' => is_bool($payload['is_healthy'] ?? null) ? $payload['is_healthy'] : null,
            'top_scores' => $this->sanitizeTopScores($payload['top_scores'] ?? []),
            'candidate_disease_name' => null,
            'candidate_disease_key' => null,
            'candidate_display_disease_name' => null,
            'candidate_severity' => null,
            'candidate_confidence_score' => null,
            'candidate_is_healthy' => null,
        ];
    }

    private function confidenceFlag(?float $confidence): string
    {
        $threshold = (float) config('services.inference.expert_high_confidence_threshold', 0.85);
        if ($confidence === null) {
            return 'low';
        }

        return $confidence >= $threshold ? 'high' : 'low';
    }

    private function isRetriableStatus(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchHealthPayload(): array
    {
        $baseUrl = rtrim((string) config('services.inference.base_url'), '/');
        $healthEndpoint = ltrim((string) config('services.inference.health_endpoint', '/health'), '/');
        $timeout = (int) config('services.inference.timeout_seconds', 15);
        $token = (string) config('services.inference.token', '');

        if ($baseUrl === '') {
            throw new RuntimeException('Inference service base URL is missing.');
        }

        $request = Http::timeout(max(2, min($timeout, 5)))
            ->acceptJson();

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get($baseUrl.'/'.$healthEndpoint);
        if (! $response->successful()) {
            throw new RuntimeException('Inference health endpoint returned '.$response->status().'.');
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $healthPayload
     * @return array<int, string>
     */
    private function contractValidationMessages(array $healthPayload): array
    {
        $messages = [];

        $expectedModelVersion = trim((string) config('services.inference.expected_model_version', ''));
        if ($expectedModelVersion !== '') {
            $actualModelVersion = trim((string) ($healthPayload['model_version'] ?? ''));
            if (strcasecmp($expectedModelVersion, $actualModelVersion) !== 0) {
                $messages[] = sprintf(
                    "model_version mismatch (expected '%s', got '%s')",
                    $expectedModelVersion,
                    $actualModelVersion
                );
            }
        }

        $expectedPixelScale = trim(strtolower((string) config('services.inference.expected_pixel_scale', '')));
        if ($expectedPixelScale !== '') {
            $actualPixelScale = trim(strtolower((string) ($healthPayload['pixel_scale'] ?? '')));
            if ($actualPixelScale !== $expectedPixelScale) {
                $messages[] = sprintf(
                    "pixel_scale mismatch (expected '%s', got '%s')",
                    $expectedPixelScale,
                    $actualPixelScale
                );
            }
        }

        $expectedLabelsCount = (int) config('services.inference.expected_labels_count', 0);
        if ($expectedLabelsCount > 0) {
            $actualLabelsCountRaw = $healthPayload['labels_count'] ?? null;
            $actualLabelsCount = is_numeric($actualLabelsCountRaw) ? (int) $actualLabelsCountRaw : null;
            if ($actualLabelsCount !== $expectedLabelsCount) {
                $messages[] = sprintf(
                    'labels_count mismatch (expected %d, got %s)',
                    $expectedLabelsCount,
                    $actualLabelsCount === null ? 'null' : (string) $actualLabelsCount
                );
            }
        }

        return $messages;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function asIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function asFloatOrNull(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function validSeverityOrNull(mixed $value): ?string
    {
        $severity = strtolower(trim((string) $value));

        return in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : null;
    }

    /**
     * @return array<int, array{label:string, score:float}>
     */
    private function sanitizeTopScores(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $scores = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = $this->nullableTrim($item['label'] ?? null);
            $score = $this->asFloatOrNull($item['score'] ?? null);
            if ($label === null || $score === null) {
                continue;
            }
            $scores[] = [
                'label' => $label,
                'score' => round(max(0.0, min(1.0, $score)), 4),
            ];
        }

        return array_slice($scores, 0, 5);
    }
}


