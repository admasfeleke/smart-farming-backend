<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DiseaseInferenceService
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
     * @return array{disease_name:string,severity:string,confidence_score:float,description:string,model_version:string}
     */
    public function analyze(int $cropId, string $imagePath): array
    {
        if (config('services.inference.enabled')) {
            return $this->analyzeWithRemoteModel($cropId, $imagePath);
        }

        // Deterministic fallback while real model service is not enabled.
        $hash = crc32($cropId.'|'.$imagePath);
        $bucket = abs($hash) % 100;

        $severity = match (true) {
            $bucket >= 90 => 'critical',
            $bucket >= 70 => 'high',
            $bucket >= 35 => 'medium',
            default => 'low',
        };

        $diseaseName = match ($cropId % 5) {
            0 => 'leaf_blight',
            1 => 'powdery_mildew',
            2 => 'rust',
            3 => 'bacterial_spot',
            default => 'healthy_or_unknown',
        };

        $confidence = round(min(0.99, max(0.45, $bucket / 100)), 2);

        return [
            'disease_name' => $diseaseName,
            'severity' => $severity,
            'confidence_score' => $confidence,
            'description' => sprintf(
                'Fallback auto-analysis result for crop_id %d. Remote inference is disabled.',
                $cropId
            ),
            'model_version' => 'fallback-v1',
        ];
    }

    /**
     * @return array{disease_name:string,severity:string,confidence_score:float,description:string,model_version:string}
     */
    private function analyzeWithRemoteModel(int $cropId, string $imagePath): array
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

        $diseaseName = trim((string) ($payload['disease_name'] ?? ''));
        $severity = trim((string) ($payload['severity'] ?? ''));
        $confidence = $payload['confidence_score'] ?? null;
        $description = trim((string) ($payload['description'] ?? ''));

        if ($diseaseName === '' || $severity === '') {
            throw new RuntimeException('Inference response missing required fields.');
        }

        if (! in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new RuntimeException('Inference severity value is invalid.');
        }

        $confidenceFloat = is_numeric($confidence) ? (float) $confidence : null;
        if ($confidenceFloat === null) {
            throw new RuntimeException('Inference confidence score is invalid.');
        }

        $confidenceFloat = max(0.0, min(1.0, $confidenceFloat));
        $modelVersion = trim((string) ($payload['model_version'] ?? ''));
        if ($modelVersion === '') {
            $modelVersion = 'remote-unknown';
        }
        $description = trim($description.' Model: '.$modelVersion);

        return [
            'disease_name' => $diseaseName,
            'severity' => $severity,
            'confidence_score' => round($confidenceFloat, 2),
            'description' => $description,
            'model_version' => $modelVersion,
        ];
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
}
