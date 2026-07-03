<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Support\DiseaseNaming;

class DiseaseReport extends Model
{
    protected $fillable = [
        'plot_id',
        'crop_id',
        'planting_id',
        'reported_by',
        'client_submission_id',
        'image_path',
        'image_disk',
        'image_mime',
        'image_size_bytes',
        'disease_name',
        'description',
        'report_source',
        'scan_metadata',
        'field_context',
        'confidence_score',
        'severity',
        'status',
        'verified_by',
        'verified_at',
        'reviewed_by',
        'reviewed_at',
        'decision_reason_code',
        'decision_comment',
        'escalated_to_user_id',
        'escalated_at',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'scan_metadata' => 'array',
        'field_context' => 'array',
        'confidence_score' => 'float',
        'image_size_bytes' => 'integer',
        'verified_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function planting()
    {
        return $this->belongsTo(Planting::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function escalatedTo()
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
    }

    public function assignments()
    {
        return $this->hasMany(CaseAssignment::class);
    }

    public function failedInferences()
    {
        return $this->hasMany(FailedInference::class);
    }

    public function latestFailedInference()
    {
        return $this->hasOne(FailedInference::class)->latestOfMany('occurred_at');
    }

    public function evidence()
    {
        return $this->hasMany(DiseaseReportEvidence::class);
    }

    public function backofficeOriginalImageSrc(): ?string
    {
        return $this->inlineImageDataUri(
            (string) ($this->image_disk ?: 'public'),
            (string) ($this->image_path ?: ''),
            $this->image_mime,
        );
    }


    public function temporaryOriginalImageUrl(?Request $request = null, int $minutes = 60): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        return $this->temporarySignedApiUrl(
            'api.v1.disease-reports.media.original',
            ['report' => $this->getKey()],
            $request,
            $minutes,
        );
    }

    public function authenticatedOriginalImageUrl(?Request $request = null): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        return $this->authenticatedApiUrl(
            'api.v1.disease-reports.media.original.authenticated',
            ['report' => $this->getKey()],
            $request,
        );
    }

    public function temporarySignedApiUrl(
        string $routeName,
        array $parameters,
        ?Request $request = null,
        int $minutes = 60,
    ): string {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes($minutes),
            $parameters,
            absolute: true,
        );
    }

    public function authenticatedApiUrl(
        string $routeName,
        array $parameters,
        ?Request $request = null,
    ): string {
        $relative = route($routeName, $parameters, false);
        if ($request !== null) {
            return rtrim($request->getSchemeAndHttpHost(), '/').$relative;
        }

        return url($relative);
    }

    public function inlineImageDataUri(string $disk, string $path, ?string $mime = null): ?string
    {
        if (trim($path) === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $resolvedMime = trim((string) $mime);
        if ($resolvedMime === '') {
            $resolvedMime = (string) (Storage::disk($disk)->mimeType($path) ?: '');
        }

        if (! str_starts_with(strtolower($resolvedMime), 'image/')) {
            return null;
        }

        return 'data:'.$resolvedMime.';base64,'.base64_encode(Storage::disk($disk)->get($path));
    }

    public function backofficeFindingName(): string
    {
        $metadata = is_array($this->scan_metadata) ? $this->scan_metadata : [];

        return $this->meaningfulDisplayName($metadata['verified_disease_name'] ?? null)
            ?? $this->meaningfulDisplayName($this->disease_name)
            ?? $this->meaningfulDisplayName($metadata['server_inference_disease_name'] ?? null)
            ?? $this->meaningfulDisplayName($metadata['offline_local_disease_name'] ?? null)
            ?? 'Awaiting analysis';
    }

    public function backofficeFindingStage(): string
    {
        $metadata = is_array($this->scan_metadata) ? $this->scan_metadata : [];
        $status = strtolower(trim((string) $this->status));

        if (in_array($status, ['confirmed', 'verified'], true) || $this->verified_at !== null) {
            return 'verified';
        }

        if ($status === 'rejected') {
            return 'rejected';
        }

        if ($status === 'processing') {
            return $this->escalated_to_user_id !== null ? 'expert review' : 'supporter review';
        }

        if ($status === 'reviewing' || $status === 'new') {
            return 'supporter review';
        }

        if ($this->meaningfulDisplayName($this->disease_name) !== null) {
            return 'server';
        }

        if ($this->meaningfulDisplayName($metadata['server_inference_disease_name'] ?? null) !== null) {
            return 'server candidate';
        }

        if ($this->meaningfulDisplayName($metadata['offline_local_disease_name'] ?? null) !== null) {
            return 'offline provisional';
        }

        return 'pending';
    }

    public function farmerVisibleStatusKey(): string
    {
        $status = strtolower(trim((string) $this->status));

        if (in_array($status, ['confirmed', 'verified'], true) || $this->verified_at !== null) {
            return 'confirmed';
        }

        if ($status === 'rejected') {
            return 'rejected';
        }

        if ($status === 'processing') {
            return $this->escalated_to_user_id !== null ? 'under_expert_review' : 'under_supporter_review';
        }

        if ($status === 'reviewing' || $status === 'new') {
            return 'under_supporter_review';
        }

        return 'awaiting_analysis';
    }

    public function farmerVisibleStatusLabel(): string
    {
        return match ($this->farmerVisibleStatusKey()) {
            'confirmed' => 'Confirmed',
            'rejected' => 'Rejected',
            'under_expert_review' => 'Under expert review',
            'under_supporter_review' => 'Under supporter review',
            default => 'Awaiting analysis',
        };
    }

    public function backofficeFindingConfidence(): ?float
    {
        $metadata = is_array($this->scan_metadata) ? $this->scan_metadata : [];

        if ($this->confidence_score !== null) {
            return (float) $this->confidence_score;
        }

        foreach (['server_inference_confidence', 'offline_local_confidence'] as $key) {
            $value = $metadata[$key] ?? null;
            if (is_numeric($value)) {
                return max(0.0, min(1.0, (float) $value));
            }
        }

        return null;
    }

    /**
     * @return array<int, array{label:string, score:float}>
     */
    public function backofficeInferenceTopScores(): array
    {
        $metadata = is_array($this->scan_metadata) ? $this->scan_metadata : [];
        $scores = $metadata['server_inference_top_scores'] ?? null;

        if (! is_array($scores) && $this->relationLoaded('latestFailedInference') && $this->latestFailedInference) {
            $payload = is_array($this->latestFailedInference->payload) ? $this->latestFailedInference->payload : [];
            $scores = $payload['top_scores'] ?? null;
        }

        if (! is_array($scores)) {
            return [];
        }

        $items = [];
        foreach ($scores as $score) {
            if (! is_array($score)) {
                continue;
            }

            $label = trim((string) ($score['label'] ?? ''));
            $value = $score['score'] ?? null;
            if ($label === '' || ! is_numeric($value)) {
                continue;
            }

            $items[] = [
                'label' => DiseaseNaming::displayLabel($label),
                'score' => max(0.0, min(1.0, (float) $value)),
            ];
        }

        return array_slice($items, 0, 5);
    }

    /**
     * @return array<int, array{label:string, value:string|null}>
     */
    public function backofficeInferenceAuditRows(): array
    {
        $metadata = is_array($this->scan_metadata) ? $this->scan_metadata : [];
        $failure = $this->relationLoaded('latestFailedInference') ? $this->latestFailedInference : null;

        return [
            [
                'label' => 'Server finding',
                'value' => $this->meaningfulDisplayName($metadata['server_inference_disease_name'] ?? null),
            ],
            [
                'label' => 'Server disease key',
                'value' => $this->nullableAuditText($metadata['server_inference_disease_key'] ?? null),
            ],
            [
                'label' => 'Server status',
                'value' => $this->nullableAuditText($metadata['server_inference_status'] ?? null),
            ],
            [
                'label' => 'Server confidence',
                'value' => $this->percentAuditText($metadata['server_inference_confidence'] ?? null),
            ],
            [
                'label' => 'Server severity',
                'value' => $this->nullableAuditText($metadata['server_inference_severity'] ?? null),
            ],
            [
                'label' => 'Healthy result',
                'value' => array_key_exists('server_inference_is_healthy', $metadata)
                    ? ((bool) $metadata['server_inference_is_healthy'] ? 'Yes' : 'No')
                    : null,
            ],
            [
                'label' => 'Rejection code',
                'value' => $this->nullableAuditText($metadata['server_inference_rejection_code'] ?? $failure?->gate_code),
            ],
            [
                'label' => 'Rejection reason',
                'value' => $this->nullableAuditText($metadata['server_inference_rejection_message'] ?? $failure?->message),
            ],
            [
                'label' => 'Model version',
                'value' => $this->nullableAuditText($failure?->model_version),
            ],
            [
                'label' => 'AI recorded at',
                'value' => $this->nullableAuditText($metadata['server_inference_recorded_at'] ?? null),
            ],
            [
                'label' => 'Offline finding',
                'value' => $this->meaningfulDisplayName($metadata['offline_local_disease_name'] ?? null),
            ],
            [
                'label' => 'Offline confidence',
                'value' => $this->percentAuditText($metadata['offline_local_confidence'] ?? null),
            ],
            [
                'label' => 'Offline model',
                'value' => $this->nullableAuditText($metadata['offline_local_model_id'] ?? $metadata['offline_local_model'] ?? null),
            ],
        ];
    }

    private function meaningfulDisplayName(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || DiseaseNaming::normalizeKey($text) === 'pending_analysis') {
            return null;
        }

        $display = DiseaseNaming::displayLabel($text);

        return trim($display) !== '' ? $display : null;
    }

    private function nullableAuditText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function percentAuditText(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(0.0, min(1.0, (float) $value)) * 100, 1).'%';
    }
}
