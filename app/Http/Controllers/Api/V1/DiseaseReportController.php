<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DiseaseReportStoreRequest;
use App\Http\Resources\Api\V1\DiseaseReportResource;
use App\Jobs\ProcessDiseaseReportScan;
use App\Models\CaseAssignment;
use App\Models\Crop;
use App\Models\DiseaseReport;
use App\Models\DiseaseReportEvidence;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\User;
use App\Services\CaseAuditLogger;
use App\Services\CaseAssignmentService;
use App\Services\DiseaseReportReviewService;
use App\Services\InferencePipelineService;
use App\Support\RegionScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiseaseReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', DiseaseReport::class);

        $user = $request->user();
        $query = DiseaseReport::query()->orderBy('reported_at', 'desc');
        $relations = [];
        if (Schema::hasTable('failed_inferences')) {
            $relations[] = 'latestFailedInference';
        }
        if (Schema::hasTable('disease_report_evidence')) {
            $relations[] = 'evidence.uploader';
        }
        if ($relations !== []) {
            $query->with($relations);
        }

        if (RegionScope::roleName($user) === 'farmer') {
            $query->whereHas('plot.farm', fn ($farmQuery) => $farmQuery->where('farmer_id', $user->id));
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('plot.farm', fn ($farmQuery) => $farmQuery->whereIn('region_id', $regionIds));
            }
        }

        return DiseaseReportResource::collection($query->paginate($this->perPage($request)));
    }

    public function show(DiseaseReport $report): DiseaseReportResource
    {
        $this->authorize('view', $report);
        $this->loadReportRelations($report);

        return new DiseaseReportResource($report);
    }

    public function store(DiseaseReportStoreRequest $request)
    {
        $this->authorize('create', DiseaseReport::class);

        $data = $request->validated();
        $submissionId = $this->resolveSubmissionId($request, $data);
        $scanMetadata = $this->normalizedScanMetadata($data['scan_metadata'] ?? null, (int) $data['crop_id']);
        $plot = Plot::findOrFail($data['plot_id']);

        $this->authorize('view', $plot);

        if (! empty($data['planting_id'])) {
            $planting = Planting::findOrFail($data['planting_id']);
            $this->authorize('view', $planting);

            if ($planting->plot_id !== $plot->id) {
                throw ValidationException::withMessages([
                    'planting_id' => ['The planting does not belong to the selected plot.'],
                ]);
            }

            if ((int) $planting->crop_id !== (int) $data['crop_id']) {
                throw ValidationException::withMessages([
                    'crop_id' => ['The selected crop does not match the selected planting.'],
                ]);
            }
        }

        $activePlantingsBase = Planting::query()
            ->where('plot_id', $plot->id)
            ->where('is_active', 1)
            ->whereIn('status', ['planned', 'active']);

        $hasActivePlantings = (clone $activePlantingsBase)->exists();
        if ($hasActivePlantings && empty($data['planting_id'])) {
            $cropRegisteredOnPlot = (clone $activePlantingsBase)
                ->where('crop_id', (int) $data['crop_id'])
                ->exists();

            if (! $cropRegisteredOnPlot) {
                throw ValidationException::withMessages([
                    'crop_id' => ['The selected crop is not registered as an active planting in this plot.'],
                ]);
            }
        }

        $existing = $this->findExistingSubmission($request->user()->id, $submissionId);
        if ($existing !== null) {
            return (new DiseaseReportResource($existing))->response()->setStatusCode(200);
        }

        try {
            $report = DB::transaction(function () use ($data, $request, $scanMetadata, $submissionId) {
                $payload = [
                    ...collect($data)->except(['scan_metadata', 'field_context', 'client_submission_id'])->all(),
                    'reported_by' => $request->user()->id,
                    'reported_at' => $data['reported_at'] ?? now(),
                    'status' => $data['status'] ?? 'new',
                    'report_source' => $data['report_source'] ?? 'manual',
                ];

                if (Schema::hasColumn('disease_reports', 'scan_metadata')) {
                    $payload['scan_metadata'] = $scanMetadata;
                }
                if (Schema::hasColumn('disease_reports', 'field_context')) {
                    $payload['field_context'] = $data['field_context'] ?? $this->fieldContextFromScanMetadata($scanMetadata);
                }
                if (Schema::hasColumn('disease_reports', 'client_submission_id')) {
                    $payload['client_submission_id'] = $submissionId;
                }

                return DiseaseReport::create($payload);
            });
        } catch (QueryException $e) {
            if ($submissionId !== null && $this->isUniqueConstraintViolation($e)) {
                $existing = $this->findExistingSubmission($request->user()->id, $submissionId);
                if ($existing !== null) {
                    return (new DiseaseReportResource($existing))->response()->setStatusCode(200);
                }
            }

            throw $e;
        }

        $this->loadReportRelations($report);
        return (new DiseaseReportResource($report))->response()->setStatusCode(201);
    }

    public function scan(Request $request, InferencePipelineService $inference)
    {
        $this->authorize('create', DiseaseReport::class);

        $this->normalizeJsonPayloadFields($request, ['scan_metadata', 'field_context']);

        $inferenceEnabled = (bool) config('services.inference.enabled', false);
        $strictInferencePrecheck = (bool) config('services.inference.strict_precheck', false);
        if ($inferenceEnabled && $strictInferencePrecheck && ! $inference->isHealthy()) {
            return response()->json([
                'message' => 'Disease inference service is unavailable. Please try again shortly.',
            ], 503);
        }

        $data = $request->validate([
            'plot_id' => ['required', 'integer', 'exists:plots,id'],
            'crop_id' => ['required', 'integer', 'exists:crops,id'],
            'planting_id' => ['nullable', 'integer', 'exists:plantings,id'],
            'client_submission_id' => ['nullable', 'string', 'max:100'],
            'captured_at' => ['nullable', 'date'],
            'scan_metadata' => ['nullable', 'array'],
            'field_context' => ['nullable', 'array'],
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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $submissionId = $this->resolveSubmissionId($request, $data);
        $scanMetadata = $this->normalizedScanMetadata($data['scan_metadata'] ?? null, (int) $data['crop_id']);

        $plot = Plot::findOrFail($data['plot_id']);
        $this->authorize('view', $plot);

        if (! empty($data['planting_id'])) {
            $planting = Planting::findOrFail($data['planting_id']);
            $this->authorize('view', $planting);

            if ($planting->plot_id !== $plot->id) {
                throw ValidationException::withMessages([
                    'planting_id' => ['The planting does not belong to the selected plot.'],
                ]);
            }

            if ((int) $planting->crop_id !== (int) $data['crop_id']) {
                throw ValidationException::withMessages([
                    'crop_id' => ['The selected crop does not match the selected planting.'],
                ]);
            }
        }

        $activePlantingsBase = Planting::query()
            ->where('plot_id', $plot->id)
            ->where('is_active', 1)
            ->whereIn('status', ['planned', 'active']);

        $hasActivePlantings = (clone $activePlantingsBase)->exists();
        if ($hasActivePlantings && empty($data['planting_id'])) {
            $cropRegisteredOnPlot = (clone $activePlantingsBase)
                ->where('crop_id', (int) $data['crop_id'])
                ->exists();

            if (! $cropRegisteredOnPlot) {
                throw ValidationException::withMessages([
                    'crop_id' => ['The selected crop is not registered as an active planting in this plot.'],
                ]);
            }
        }

        $existing = $this->findExistingSubmission($request->user()->id, $submissionId);
        if ($existing !== null) {
            return (new DiseaseReportResource($existing))->response()->setStatusCode(200);
        }

        /** @var UploadedFile $imageFile */
        $imageFile = $request->file('image');
        $imagePath = $imageFile->store('disease-reports', 'public');

        try {
            $report = DB::transaction(function () use ($data, $request, $scanMetadata, $submissionId, $imagePath, $imageFile) {
                $payload = [
                    'plot_id' => $data['plot_id'],
                    'crop_id' => $data['crop_id'],
                    'planting_id' => $data['planting_id'] ?? null,
                    'reported_by' => $request->user()->id,
                    'disease_name' => 'pending_analysis',
                    'report_source' => 'ai',
                    'description' => null,
                    'confidence_score' => null,
                    'severity' => 'low',
                    'status' => 'reviewing',
                    'reported_at' => $data['captured_at'] ?? now(),
                ];

                if (Schema::hasColumn('disease_reports', 'scan_metadata')) {
                    $payload['scan_metadata'] = $scanMetadata;
                }
                if (Schema::hasColumn('disease_reports', 'field_context')) {
                    $payload['field_context'] = $data['field_context'] ?? $this->fieldContextFromScanMetadata($scanMetadata);
                }
                if (Schema::hasColumn('disease_reports', 'client_submission_id')) {
                    $payload['client_submission_id'] = $submissionId;
                }
                if (Schema::hasColumn('disease_reports', 'image_path')) {
                    $payload['image_path'] = $imagePath;
                }
                if (Schema::hasColumn('disease_reports', 'image_disk')) {
                    $payload['image_disk'] = 'public';
                }
                if (Schema::hasColumn('disease_reports', 'image_mime')) {
                    $payload['image_mime'] = $imageFile->getMimeType();
                }
                if (Schema::hasColumn('disease_reports', 'image_size_bytes')) {
                    $payload['image_size_bytes'] = $imageFile->getSize();
                }

                $report = DiseaseReport::create($payload);

                if (Schema::hasTable('disease_report_evidence')) {
                    DiseaseReportEvidence::create([
                        'disease_report_id' => $report->id,
                        'uploaded_by' => $request->user()->id,
                        'kind' => 'original_capture',
                        'file_path' => $imagePath,
                        'file_disk' => 'public',
                        'mime_type' => $imageFile->getMimeType(),
                        'size_bytes' => $imageFile->getSize(),
                        'caption' => 'Original field capture',
                    ]);
                }

                return $report;
            });
        } catch (QueryException $e) {
            if ($submissionId !== null && $this->isUniqueConstraintViolation($e)) {
                Storage::disk('public')->delete($imagePath);
                $existing = $this->findExistingSubmission($request->user()->id, $submissionId);
                if ($existing !== null) {
                    return (new DiseaseReportResource($existing))->response()->setStatusCode(200);
                }
            }

            throw $e;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($imagePath);
            throw $e;
        }

        Log::info('Disease scan report stored with image evidence', [
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'plot_id' => $report->plot_id,
            'crop_id' => $report->crop_id,
            'image_path' => $imagePath,
            'client_submission_id' => $submissionId,
        ]);

        app(CaseAssignmentService::class)->autoAssignDiseaseReport($report);

        try {
            ProcessDiseaseReportScan::dispatch($report->id, $imagePath)
                ->onConnection('database')
                ->onQueue('inference');
        } catch (\Throwable $e) {
            Log::warning('Disease scan queued report kept for manual review after dispatch failure', [
                'report_id' => $report->id,
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            $report->forceFill([
                'status' => 'reviewing',
                'description' => trim(
                    (string) ($report->description ?? '')
                    .' Inference queue dispatch failed; manual review required.'
                ),
            ])->save();
        }

        $this->loadReportRelations($report);
        return (new DiseaseReportResource($report))->response()->setStatusCode(201);
    }

    public function verify(Request $request, DiseaseReport $report): DiseaseReportResource
    {
        $this->authorize('verify', $report);
        $role = RegionScope::roleName($request->user());
        if (! in_array($role, ['supporter', 'expert'], true)) {
            abort(403, 'Only supporter or expert roles can review disease reports.');
        }

        $strictAccountability = filter_var(env('ENFORCE_ACCOUNTABILITY_FIELDS', false), FILTER_VALIDATE_BOOL);

        $data = $request->validate([
            'disease_name' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'description' => ['nullable', 'string'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'status' => ['required', 'string', 'in:confirmed,rejected'],
            'decision_reason_code' => [Rule::requiredIf($strictAccountability), 'nullable', 'string', 'max:60'],
            'decision_comment' => [Rule::requiredIf($strictAccountability), 'nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'evidence_caption' => ['nullable', 'string', 'max:500'],
        ]);

        $decisionReasonCode = $data['decision_reason_code']
            ?? ($role === 'expert'
                ? ($data['status'] === 'confirmed' ? 'expert_confirmed' : 'expert_rejected')
                : ($data['status'] === 'confirmed' ? 'supporter_triage' : 'supporter_triage_reject'));
        $decisionComment = $data['decision_comment']
            ?? ($data['description'] ?? ($role === 'expert' ? 'Expert decision recorded.' : 'Supporter triage recorded.'));

        $reviewService = app(DiseaseReportReviewService::class);

        if ($role === 'supporter') {
            $report = $reviewService->triage(
                $report,
                $request->user(),
                $data['status'],
                $data['confidence_score'] ?? $report->confidence_score,
                $decisionReasonCode,
                $decisionComment,
            );
        } else {
            $report = DB::transaction(function () use ($report, $request, $data, $decisionReasonCode, $decisionComment, $reviewService) {
                $reviewedReport = $data['status'] === 'confirmed'
                    ? $reviewService->confirm(
                        $report,
                        $request->user(),
                        $data['disease_name'],
                        $data['severity'],
                        $data['confidence_score'] ?? null,
                        $decisionReasonCode,
                        $decisionComment,
                    )
                    : $reviewService->reject(
                        $report,
                        $request->user(),
                        $decisionReasonCode,
                        $decisionComment,
                    );

                return $reviewedReport;
            });
        }

        if ($request->hasFile('evidence') && Schema::hasTable('disease_report_evidence')) {
            $kind = $role === 'supporter'
                ? 'supporter_triage_evidence'
                : (($data['status'] ?? '') === 'confirmed'
                    ? 'expert_annotation'
                    : 'rejection_evidence');
            $caption = trim((string) ($data['evidence_caption'] ?? ''));
            if ($caption === '') {
                $caption = $decisionComment;
            }
            $this->storeReviewEvidence(
                $report,
                $request->file('evidence'),
                $request->user()->id,
                $kind,
                $caption
            );
        }

        $report = $report->fresh();
        $this->loadReportRelations($report);
        return new DiseaseReportResource($report);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return max(1, min($perPage, 100));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveSubmissionId(Request $request, array $data): ?string
    {
        $headerKey = trim((string) $request->header('Idempotency-Key', ''));
        $bodyKey = trim((string) ($data['client_submission_id'] ?? ''));
        $key = $headerKey !== '' ? $headerKey : $bodyKey;

        if ($key === '') {
            return null;
        }

        return mb_substr($key, 0, 100);
    }

    private function findExistingSubmission(int $userId, ?string $submissionId): ?DiseaseReport
    {
        if ($submissionId === null || ! Schema::hasColumn('disease_reports', 'client_submission_id')) {
            return null;
        }

        $query = DiseaseReport::query()
            ->where('reported_by', $userId)
            ->where('client_submission_id', $submissionId);
        $relations = [];
        if (Schema::hasTable('failed_inferences')) {
            $relations[] = 'latestFailedInference';
        }
        if (Schema::hasTable('disease_report_evidence')) {
            $relations[] = 'evidence.uploader';
        }
        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->first();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if (in_array($sqlState, ['23000', '23505'], true)) {
            return true;
        }
        if (in_array($driverCode, [19, 1062], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>|null
     */
    private function normalizedScanMetadata(?array $raw, ?int $cropId = null): ?array
    {
        $raw = is_array($raw) ? $raw : [];
        $allowed = [
            'growth_stage',
            'symptom_days',
            'recent_rain',
            'field_notes',
            'capture_shots',
            'capture_protocol',
            'expected_capture_shots',
            'offline_local_disease_name',
            'offline_local_disease_key',
            'offline_local_severity',
            'offline_local_confidence',
            'offline_local_model_id',
            'offline_local_model',
            'offline_local_provisional',
            'offline_local_inference',
            'offline_local_inference_unavailable',
        ];
        $metadata = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }

            $value = $raw[$key];
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }

            if ($value === null) {
                continue;
            }

            $metadata[$key] = $value;
        }

        $expectedShots = $this->expectedCaptureShotsForCropId($cropId);
        if ($expectedShots !== null) {
            $metadata['expected_capture_shots'] = $expectedShots;
        }

        return $metadata === [] ? null : $metadata;
    }

    private function expectedCaptureShotsForCropId(?int $cropId): ?int
    {
        if ($cropId === null || $cropId <= 0) {
            return 2;
        }

        $crop = Crop::query()->find($cropId);
        $family = $this->cropFamilyFromName($crop?->name);
        $familyShots = [
            'tomato' => 3,
            'potato' => 3,
            'pepper' => 3,
            'grape' => 3,
        ];

        return $family !== null && array_key_exists($family, $familyShots)
            ? (int) $familyShots[$family]
            : 2;
    }

    private function loadReportRelations(DiseaseReport $report): void
    {
        $relations = [];
        if (Schema::hasTable('failed_inferences')) {
            $relations[] = 'latestFailedInference';
        }
        if (Schema::hasTable('disease_report_evidence')) {
            $relations[] = 'evidence.uploader';
        }
        if ($relations !== []) {
            $report->loadMissing($relations);
        }
    }

    private function autoAssignReportForReview(DiseaseReport $report): void
    {
        if (CaseAssignment::query()
            ->where('disease_report_id', $report->id)
            ->where('status', 'active')
            ->exists()) {
            return;
        }

        $report->loadMissing('plot.farm');
        $regionId = $report->plot?->farm?->region_id;
        $assignee = $this->reviewerForRegion($regionId);

        if (! $assignee instanceof User) {
            Log::warning('Disease report left unassigned: no active reviewer found for region', [
                'report_id' => $report->id,
                'region_id' => $regionId,
            ]);
            return;
        }

        CaseAssignment::query()->create([
            'disease_report_id' => $report->id,
            'assigned_to_user_id' => $assignee->id,
            'assigned_by_user_id' => $this->systemAssignmentActorId() ?? (int) auth()->id(),
            'priority' => in_array($report->severity, ['high', 'critical'], true) ? 'high' : 'normal',
            'status' => 'active',
        ]);

        $report->forceFill([
            'escalated_to_user_id' => $assignee->id,
            'escalated_at' => now(),
        ])->save();

        CaseAuditLogger::log(
            'disease_report',
            $report->id,
            'auto_assign',
            $report->status,
            $report->status,
            'Automatically assigned to regional reviewer after scan upload.',
            ['assigned_to_user_id' => $assignee->id, 'region_id' => $regionId],
        );
    }

    private function reviewerForRegion(?int $regionId): ?User
    {
        $candidates = User::query()
            ->where('is_active', 1)
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['expert', 'supporter']))
            ->with(['role', 'scopedRegions'])
            ->orderByRaw("
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM roles
                        WHERE roles.id = users.role_id AND roles.name = 'expert'
                    ) THEN 0
                    ELSE 1
                END
            ")
            ->orderBy('id')
            ->get();

        if ($regionId === null) {
            return $candidates->first();
        }

        return $candidates
            ->map(function (User $candidate) use ($regionId): array {
                return [
                    'user' => $candidate,
                    'distance' => RegionScope::scopeMatchDistance($candidate, (int) $regionId),
                    'role_priority' => RegionScope::roleName($candidate) === 'expert' ? 0 : 1,
                ];
            })
            ->filter(fn (array $item): bool => $item['distance'] !== null)
            ->sort(function (array $a, array $b): int {
                return ($a['distance'] <=> $b['distance'])
                    ?: ($a['role_priority'] <=> $b['role_priority'])
                    ?: ($a['user']->id <=> $b['user']->id);
            })
            ->pluck('user')
            ->first();
    }

    private function systemAssignmentActorId(): ?int
    {
        return User::query()
            ->where('is_active', 1)
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['super_admin', 'admin']))
            ->orderByRaw("
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM roles
                        WHERE roles.id = users.role_id AND roles.name = 'super_admin'
                    ) THEN 0
                    ELSE 1
                END
            ")
            ->orderBy('id')
            ->value('id');
    }

    private function storeReviewEvidence(
        DiseaseReport $report,
        UploadedFile $file,
        int $userId,
        string $kind,
        ?string $caption = null
    ): void {
        if (! Schema::hasTable('disease_report_evidence')) {
            return;
        }

        $path = $file->store($this->reviewEvidenceDirectory($report), 'public');

        DiseaseReportEvidence::create([
            'disease_report_id' => $report->id,
            'uploaded_by' => $userId,
            'kind' => $kind,
            'file_path' => $path,
            'file_disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'caption' => $caption,
        ]);
    }

    private function reviewEvidenceDirectory(DiseaseReport $report): string
    {
        return 'disease-reports/review/'.$report->id;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function mergeReviewNamingMetadata(array $metadata, string $status, string $diseaseName): array
    {
        $trimmedDiseaseName = trim($diseaseName);
        if ($trimmedDiseaseName === '') {
            return $metadata;
        }

        if ($status === 'confirmed') {
            $metadata['verified_disease_name'] = $trimmedDiseaseName;
            $metadata['verified_disease_key'] = $this->normalizeDiseaseKey($trimmedDiseaseName);
            $metadata['verified_decision_status'] = 'confirmed';
            $metadata['verified_recorded_at'] = now()->toISOString();
            return $metadata;
        }

        unset($metadata['verified_disease_name'], $metadata['verified_disease_key'], $metadata['verified_recorded_at']);
        $metadata['verified_decision_status'] = 'rejected';

        return $metadata;
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

    private function cropFamilyFromName(?string $cropName): ?string
    {
        if ($cropName === null) {
            return null;
        }

        $trimmed = trim(strtolower($cropName));
        if ($trimmed === '') {
            return null;
        }

        $normalized = (string) preg_replace('/[^a-z0-9]+/', '_', $trimmed);
        $normalized = trim((string) preg_replace('/_+/', '_', $normalized), '_');
        if ($normalized === '') {
            return null;
        }

        $family = explode('_', $normalized)[0] ?? null;
        if ($family === 'maize') {
            return 'corn';
        }

        return $family;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fieldContextFromScanMetadata(array $metadata): ?array
    {
        $context = [];
        foreach (['growth_stage', 'symptom_days', 'recent_rain', 'field_notes'] as $key) {
            if (array_key_exists($key, $metadata) && $metadata[$key] !== null && $metadata[$key] !== '') {
                $context[$key] = $metadata[$key];
            }
        }

        return $context === [] ? null : $context;
    }

    private function normalizeJsonPayloadFields(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $request->input($field);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge([$field => $decoded]);
            }
        }
    }
}
