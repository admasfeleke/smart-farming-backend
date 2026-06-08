<?php

namespace App\Http\Resources\Api\V1;

use App\Services\DiseaseTreatmentGuidanceService;
use App\Support\ApiLocalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DiseaseReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $guidance = ApiLocalizer::localizeTreatmentGuidance(
            $request,
            app(DiseaseTreatmentGuidanceService::class)->build($this->resource)
        );
        $latestFailure = $this->relationLoaded('latestFailedInference')
            ? $this->latestFailedInference
            : null;
        $evidence = $this->relationLoaded('evidence') ? $this->evidence : collect();
        $isAuthenticatedApiRequest = $request->user() !== null;
        $imageUrl = $isAuthenticatedApiRequest
            ? ($this->resource->backofficeOriginalImageSrc()
                ?? $this->resource->authenticatedOriginalImageUrl($request))
            : $this->resource->temporaryOriginalImageUrl($request);
        $rawDiseaseName = trim((string) ($this->disease_name ?? ''));
        $scanMetadata = is_array($this->scan_metadata) ? $this->scan_metadata : [];
        $provisionalDisplayName = $this->meaningfulDiseaseName($scanMetadata['offline_local_disease_name'] ?? null);
        $provisionalCanonicalName = $this->meaningfulDiseaseKey($scanMetadata['offline_local_disease_key'] ?? null);
        $inferredDisplayName = $this->meaningfulDiseaseName($scanMetadata['server_inference_disease_name'] ?? null);
        $inferredCanonicalName = $this->meaningfulDiseaseKey($scanMetadata['server_inference_disease_key'] ?? null);
        $verifiedDisplayName = $this->meaningfulDiseaseName($scanMetadata['verified_disease_name'] ?? null);
        $verifiedCanonicalName = $this->meaningfulDiseaseKey($scanMetadata['verified_disease_key'] ?? null);
        $storedDisplayName = $this->meaningfulDiseaseName($rawDiseaseName);
        $storedCanonicalName = $this->meaningfulDiseaseKey($rawDiseaseName);
        $status = strtolower(trim((string) ($this->status ?? '')));
        $hasVerifiedDecision = in_array($status, ['confirmed', 'verified'], true)
            || $this->verified_at !== null
            || $this->reviewed_at !== null;
        $namingStage = $this->namingStage(
            $hasVerifiedDecision,
            $verifiedDisplayName,
            $storedDisplayName,
            $storedCanonicalName,
            $inferredDisplayName,
            $provisionalDisplayName
        );
        $displayDiseaseName = $this->historyDisplayDiseaseName(
            $namingStage,
            $verifiedDisplayName,
            $storedDisplayName,
            $inferredDisplayName,
            $provisionalDisplayName
        );
        $canonicalDiseaseName = $this->historyCanonicalDiseaseName(
            $namingStage,
            $verifiedCanonicalName,
            $storedCanonicalName,
            $inferredCanonicalName,
            $provisionalCanonicalName
        );
        $likelyIssueName = $inferredDisplayName ?? $provisionalDisplayName;
        $likelyIssueCanonicalName = $inferredCanonicalName ?? $provisionalCanonicalName;

        return [
            'id' => $this->id,
            'plot_id' => $this->plot_id,
            'crop_id' => $this->crop_id,
            'planting_id' => $this->planting_id,
            'client_submission_id' => $this->client_submission_id,
            'disease_name' => $this->disease_name,
            'display_disease_name' => $displayDiseaseName,
            'canonical_disease_name' => $canonicalDiseaseName,
            'naming_stage' => $namingStage,
            'provisional_disease_name' => $provisionalDisplayName,
            'provisional_canonical_disease_name' => $provisionalCanonicalName,
            'inferred_disease_name' => $inferredDisplayName,
            'inferred_canonical_disease_name' => $inferredCanonicalName,
            'verified_disease_name' => $verifiedDisplayName,
            'verified_canonical_disease_name' => $verifiedCanonicalName,
            'likely_issue_name' => $likelyIssueName,
            'likely_issue_canonical_disease_name' => $likelyIssueCanonicalName,
            'description' => $this->description,
            'report_source' => $this->report_source,
            'scan_metadata' => $this->scan_metadata,
            'field_context' => $this->field_context,
            'confidence_score' => $this->confidence_score,
            'severity' => $this->severity,
            'status' => $this->status,
            'decision_reason_code' => $this->decision_reason_code,
            'decision_comment' => $this->decision_comment,
            'reviewed_at' => optional($this->reviewed_at)->toISOString(),
            'verified_at' => optional($this->verified_at)->toISOString(),
            'reported_at' => optional($this->reported_at)->toISOString()
                ?? optional($this->created_at)->toISOString(),
            'original_image_url' => $imageUrl,
            'image_mime' => $this->image_mime,
            'image_size_bytes' => $this->image_size_bytes,
            'inference_failure' => $latestFailure === null ? null : [
                'code' => $latestFailure->gate_code,
                'gate' => $latestFailure->gate_stage,
                'selected' => $latestFailure->selected_crop,
                'detected' => $latestFailure->detected_crop,
                'message' => $latestFailure->message,
                'confidence_score' => $latestFailure->confidence_score,
                'occurred_at' => optional($latestFailure->occurred_at)->toISOString()
                    ?? optional($latestFailure->created_at)->toISOString(),
            ],
            'evidence' => $evidence->map(function ($item) use ($request, $isAuthenticatedApiRequest) {
                return [
                    'id' => $item->id,
                    'kind' => $item->kind,
                    'url' => $isAuthenticatedApiRequest
                        ? ($item->backofficePreviewSrc() ?? $item->authenticatedUrl($request))
                        : $item->temporaryUrl($request),
                    'mime_type' => $item->mime_type,
                    'caption' => $item->caption,
                    'uploaded_at' => optional($item->created_at)->toISOString(),
                    'uploaded_by_name' => optional($item->uploader)->name,
                ];
            })->values()->all(),
            'treatment_guidance' => $guidance,
        ];
    }

    private function namingStage(
        bool $hasVerifiedDecision,
        ?string $verifiedDisplayName,
        ?string $storedDisplayName,
        ?string $storedCanonicalName,
        ?string $inferredDisplayName,
        ?string $provisionalDisplayName
    ): string {
        // A confirmed/rejected decision always means the case was reviewed,
        // even if the disease name was not updated (legacy backoffice behaviour).
        if ($hasVerifiedDecision) {
            return 'verified';
        }

        if ($storedDisplayName !== null && $storedCanonicalName !== null) {
            return 'inferred';
        }

        if ($inferredDisplayName !== null) {
            return 'inferred';
        }

        if ($provisionalDisplayName !== null) {
            return 'provisional';
        }

        return 'pending';
    }

    private function historyDisplayDiseaseName(
        string $namingStage,
        ?string $verifiedDisplayName,
        ?string $storedDisplayName,
        ?string $inferredDisplayName,
        ?string $provisionalDisplayName
    ): ?string {
        return match ($namingStage) {
            'verified' => $verifiedDisplayName ?? $storedDisplayName ?? $inferredDisplayName ?? $provisionalDisplayName,
            'inferred' => $storedDisplayName ?? $inferredDisplayName ?? $provisionalDisplayName,
            'provisional' => $provisionalDisplayName,
            default => null,
        };
    }

    private function historyCanonicalDiseaseName(
        string $namingStage,
        ?string $verifiedCanonicalName,
        ?string $storedCanonicalName,
        ?string $inferredCanonicalName,
        ?string $provisionalCanonicalName
    ): string {
        return match ($namingStage) {
            'verified' => $verifiedCanonicalName ?? $storedCanonicalName ?? $inferredCanonicalName ?? $provisionalCanonicalName ?? 'pending_analysis',
            'inferred' => $storedCanonicalName ?? $inferredCanonicalName ?? $provisionalCanonicalName ?? 'pending_analysis',
            'provisional' => $provisionalCanonicalName ?? 'pending_analysis',
            default => 'pending_analysis',
        };
    }

    private function meaningfulDiseaseKey(?string $value): ?string
    {
        $normalized = $this->normalizeDiseaseKey($value);
        return $normalized === 'pending_analysis' ? null : $normalized;
    }

    private function meaningfulDiseaseName(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '' || $this->normalizeDiseaseKey($trimmed) === 'pending_analysis') {
            return null;
        }

        return $this->humanizeDiseaseName($trimmed);
    }

    private function normalizeDiseaseKey(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = (string) preg_replace('/[\s\-\/()]+/', '_', $normalized);
        $normalized = (string) preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        $normalized = trim((string) preg_replace('/_+/', '_', $normalized), '_');

        $pendingAliases = [
            '',
            'pending_analysis',
            'analysis_pending',
            'pending',
            'processing',
            'queued',
            'submitted',
            'manual_review_required',
            'unknown',
            'unknown_issue',
            'healthy_or_unknown',
        ];

        if (in_array($normalized, $pendingAliases, true)) {
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

    private function humanizeDiseaseName(string $value): string
    {
        $normalized = $this->normalizeDiseaseKey($value);
        if ($normalized === 'pending_analysis') {
            return '';
        }

        $parts = array_values(array_filter(explode('_', $normalized)));
        $words = array_map(function (string $word): string {
            return match ($word) {
                'ph' => 'pH',
                'ppi' => 'PPI',
                'rei' => 'REI',
                default => strlen($word) <= 2 ? strtoupper($word) : ucfirst($word),
            };
        }, $parts);

        return implode(' ', $words);
    }
}

