<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SoilHealth;
use App\Models\Plot;
use App\Models\Farm;
use App\Models\Region;
use App\Models\User;
use App\Services\InferencePipelineService;
use App\Services\CaseAssignmentService;
use App\Support\ApiLocalizer;
use App\Support\RegionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SoilHealthController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SoilHealth::class);

        $user = $request->user();
        $query = SoilHealth::query()->with(['testedBy', 'reviewedBy']);

        // Apply region scoping for non-farmers
        if (RegionScope::roleName($user) !== 'farmer') {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('plot_id', function ($subQuery) use ($regionIds) {
                    $subQuery->select('id')
                        ->from('plots')
                        ->whereIn('farm_id', function ($farmQuery) use ($regionIds) {
                            $farmQuery->select('id')
                                ->from('farms')
                                ->whereIn('region_id', $regionIds);
                        });
                });
            }
        } else {
            // Farmers can only see their own data
            $query->whereHas('plot.farm', fn ($farmQuery) => $farmQuery->where('farmer_id', $user->id));
        }

        // Filter by test date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            try {
                $query->whereBetween('test_date', [$startDate, $endDate]);
            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    'date_range' => ['Invalid date range provided.'],
                ]);
            }
        }

        // Filter by test method
        if ($request->has('test_method')) {
            $query->where('test_method', $request->input('test_method'));
        }

        // Filter by plot
        if ($request->has('plot_id')) {
            $plotId = $request->input('plot_id');
            $this->validatePlotAccess($user, $plotId);
            $query->where('plot_id', $plotId);
        }

        $data = $query->orderBy('test_date', 'desc')->paginate($this->perPage($request));

        return response()->json([
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }

    public function show(SoilHealth $soilHealth)
    {
        $this->authorize('view', $soilHealth);
        return response()->json($soilHealth->load(['testedBy', 'reviewedBy']));
    }

    public function store(Request $request)
    {
        $this->authorize('create', SoilHealth::class);

        $this->normalizeJsonPayloadFields($request, ['sensor_payload', 'field_context']);

        $user = $request->user();
        $role = RegionScope::roleName($user);
        $isReviewer = in_array($role, ['super_admin', 'admin', 'supporter', 'expert', 'field_officer'], true);

        $data = $request->validate([
            'plot_id' => ['required', 'integer', 'exists:plots,id'],
            'ph_level' => ['nullable', 'numeric', 'min:3', 'max:10'],
            'nitrogen' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'phosphorus' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'potassium' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'organic_matter' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'soil_type' => ['nullable', 'string', 'max:50'],
            'moisture_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'test_date' => ['nullable', 'date'],
            'recommendations' => ['nullable', 'string'],
            'test_method' => ['required', 'string', 'max:50'],
            'data_source' => ['nullable', 'string', 'max:50'],
            'sensor_device_id' => ['nullable', 'string', 'max:120'],
            'sensor_reading_id' => ['nullable', 'string', 'max:160'],
            'sensor_payload' => ['nullable', 'array'],
            'field_context' => ['nullable', 'array'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tested_by' => ['nullable', 'integer', 'exists:users,id'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        // Validate access to plot
        $this->validatePlotAccess($user, $data['plot_id']);

        if (empty($data['test_date'])) {
            $data['test_date'] = now()->toDateString();
        }
        $data['data_source'] = $data['data_source'] ?? $data['test_method'] ?? 'manual';

        // Set tested_by to current user if not provided
        if (empty($data['tested_by'])) {
            $data['tested_by'] = $user->id;
        }

        $data['review_status'] = $isReviewer ? 'validated' : 'pending';
        $data['reviewed_by'] = $isReviewer ? $user->id : null;
        $data['reviewed_at'] = $isReviewer ? now() : null;

        $soilHealth = SoilHealth::create($data);
        app(CaseAssignmentService::class)->autoAssignSoilHealth($soilHealth);

        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
            $filename = sprintf('%s_%s.%s', $soilHealth->id, Str::uuid(), $ext);
            $path = $file->storeAs(
                $this->evidenceDirectory($soilHealth),
                $filename,
                'public'
            );

            $soilHealth->evidence_url = Storage::disk('public')->url($path);
            $soilHealth->evidence_type = $file->getClientMimeType() ?: $ext;
            $soilHealth->save();
        }

        return response()->json($soilHealth->load(['testedBy', 'reviewedBy']), 201);
    }

    public function update(Request $request, SoilHealth $soilHealth)
    {
        $this->authorize('update', $soilHealth);

        $this->normalizeJsonPayloadFields($request, ['sensor_payload', 'field_context']);

        $user = $request->user();
        $role = RegionScope::roleName($user);
        $isReviewer = in_array($role, ['super_admin', 'admin', 'supporter', 'expert', 'field_officer'], true);

        $data = $request->validate([
            'ph_level' => ['nullable', 'numeric', 'min:3', 'max:10'],
            'nitrogen' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'phosphorus' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'potassium' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'organic_matter' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'soil_type' => ['nullable', 'string', 'max:50'],
            'moisture_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'test_date' => ['date'],
            'recommendations' => ['nullable', 'string'],
            'test_method' => ['string', 'max:50'],
            'data_source' => ['nullable', 'string', 'max:50'],
            'sensor_device_id' => ['nullable', 'string', 'max:120'],
            'sensor_reading_id' => ['nullable', 'string', 'max:160'],
            'sensor_payload' => ['nullable', 'array'],
            'field_context' => ['nullable', 'array'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'review_status' => ['sometimes', 'string', Rule::in(['pending', 'validated', 'rejected'])],
            'review_reason_code' => ['nullable', 'string', 'max:80'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        if (! $isReviewer) {
            unset($data['review_status']);
            unset($data['review_reason_code']);
            unset($data['review_comment']);
            $data['review_status'] = 'pending';
            $data['reviewed_by'] = null;
            $data['reviewed_at'] = null;
        } elseif (array_key_exists('review_status', $data)) {
            if ($data['review_status'] === 'validated') {
                $data['reviewed_by'] = $user->id;
                $data['reviewed_at'] = now();
            } elseif ($data['review_status'] === 'pending') {
                $data['reviewed_by'] = null;
                $data['reviewed_at'] = null;
                $data['review_reason_code'] = null;
                $data['review_comment'] = null;
            } elseif ($data['review_status'] === 'rejected') {
                $data['reviewed_by'] = $user->id;
                $data['reviewed_at'] = now();
            }
        }

        $soilHealth->update($data);

        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
            $filename = sprintf('%s_%s.%s', $soilHealth->id, Str::uuid(), $ext);
            $path = $file->storeAs(
                $this->evidenceDirectory($soilHealth),
                $filename,
                'public'
            );

            $soilHealth->evidence_url = Storage::disk('public')->url($path);
            $soilHealth->evidence_type = $file->getClientMimeType() ?: $ext;
            $soilHealth->save();
            $this->pruneEvidenceFiles($soilHealth, $path);
        }

        return response()->json($soilHealth->load(['testedBy', 'reviewedBy']));
    }

    public function destroy(SoilHealth $soilHealth)
    {
        $this->authorize('delete', $soilHealth);
        $soilHealth->delete();
        $this->deleteEvidenceDirectory($soilHealth);
        return response()->json(['message' => ApiLocalizer::message(request(), 'soil_deleted')]);
    }

    public function summary(Request $request)
    {
        $this->authorize('viewAny', SoilHealth::class);

        $user = $request->user();
        $query = SoilHealth::query();

        // Apply region scoping
        if (RegionScope::roleName($user) !== 'farmer') {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                return response()->json(['summary' => []]);
            }
            $query->whereIn('plot_id', function ($subQuery) use ($regionIds) {
                $subQuery->select('id')
                    ->from('plots')
                    ->whereIn('farm_id', function ($farmQuery) use ($regionIds) {
                        $farmQuery->select('id')
                            ->from('farms')
                            ->whereIn('region_id', $regionIds);
                    });
            });
        } else {
            $query->whereHas('plot.farm', fn ($farmQuery) => $farmQuery->where('farmer_id', $user->id));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $query->whereBetween('test_date', [$startDate, $endDate]);
        }

        $summary = $query->selectRaw('
            AVG(ph_level) as avg_ph,
            MIN(ph_level) as min_ph,
            MAX(ph_level) as max_ph,
            AVG(nitrogen) as avg_nitrogen,
            AVG(phosphorus) as avg_phosphorus,
            AVG(potassium) as avg_potassium,
            AVG(organic_matter) as avg_organic_matter,
            AVG(moisture_level) as avg_moisture,
            COUNT(*) as total_tests
        ')->first();

        return response()->json(['summary' => $summary]);
    }

    public function recommendations(Request $request, SoilHealth $soilHealth)
    {
        $this->authorize('view', $soilHealth);

        $soilHealth->loadMissing(['plot.plantings.crop', 'plot.farm']);
        $analysis = $this->analyzeSoilHealth($soilHealth);
        $status = strtolower((string) $soilHealth->review_status);
        $provisional = $status !== 'validated';
        $notice = $provisional
            ? ApiLocalizer::message($request, 'soil_notice_provisional')
            : null;
        $analysis = ApiLocalizer::localizeSoilAnalysis($request, $analysis);

        return response()->json([
            'soil_health' => $soilHealth,
            'recommendations' => $analysis['actions'],
            'analysis' => $analysis,
            'review_status' => $status,
            'provisional' => $provisional,
            'notice' => $notice,
        ]);
    }

    protected function analyzeSoilHealth(SoilHealth $soilHealth): array
    {
        $activePlanting = $soilHealth->plot?->plantings
            ?->first(fn ($planting) => (bool) $planting->is_active || strtolower((string) $planting->status) === 'active');
        $cropName = trim((string) ($activePlanting?->crop?->name ?? ''));
        $cropProfile = $this->cropProfileFor($cropName);
        $previous = SoilHealth::query()
            ->where('plot_id', $soilHealth->plot_id)
            ->whereKeyNot($soilHealth->getKey())
            ->whereNotNull('test_date')
            ->orderByDesc('test_date')
            ->orderByDesc('id')
            ->first();

        $issues = [];
        $actions = [];
        $watch = [];

        $ph = $this->assessRange(
            'Soil pH',
            $soilHealth->ph_level,
            $cropProfile['ph_min'],
            $cropProfile['ph_max'],
            0.4,
            0.5,
            lowAction: 'Apply lime gradually and retest before the next fertilizer cycle.',
            highAction: 'Use organic matter or acidifying amendments in small steps and retest.',
            goodNote: 'Soil pH is within the working range for the current crop context.',
        );
        $this->appendAssessment($issues, $actions, $watch, $ph);

        $nitrogen = $this->assessRange(
            'Nitrogen',
            $soilHealth->nitrogen,
            $cropProfile['nitrogen_min'],
            $cropProfile['nitrogen_max'],
            10,
            20,
            lowAction: $cropName !== ''
                ? "Increase nitrogen carefully for {$cropName}, split across growth stages instead of one heavy application."
                : 'Increase nitrogen with split feeding instead of a single heavy application.',
            highAction: 'Hold extra nitrogen for now and irrigate carefully to avoid further imbalance.',
            goodNote: 'Nitrogen is within the working range for the current crop stage.',
        );
        $this->appendAssessment($issues, $actions, $watch, $nitrogen);

        $phosphorus = $this->assessRange(
            'Phosphorus',
            $soilHealth->phosphorus,
            $cropProfile['phosphorus_min'],
            $cropProfile['phosphorus_max'],
            5,
            10,
            lowAction: 'Prioritize phosphorus placement near the root zone before the next irrigation.',
            highAction: 'Avoid extra phosphorus and monitor runoff risk after rain or irrigation.',
            goodNote: 'Phosphorus is adequate for root support and flowering.',
        );
        $this->appendAssessment($issues, $actions, $watch, $phosphorus);

        $potassium = $this->assessRange(
            'Potassium',
            $soilHealth->potassium,
            $cropProfile['potassium_min'],
            $cropProfile['potassium_max'],
            20,
            50,
            lowAction: 'Increase potassium to strengthen stress tolerance and crop quality.',
            highAction: 'Pause potassium additions and watch for salt buildup symptoms.',
            goodNote: 'Potassium is broadly supportive of crop resilience.',
        );
        $this->appendAssessment($issues, $actions, $watch, $potassium);

        $organicMatter = $this->assessRange(
            'Organic matter',
            $soilHealth->organic_matter,
            2.5,
            8.0,
            0.5,
            1.0,
            lowAction: 'Add compost, manure, or cover-crop residue to improve structure and nutrient holding.',
            highAction: 'Organic matter is high; maintain current practices and avoid overloading fresh material.',
            goodNote: 'Organic matter is supporting structure and nutrient buffering.',
        );
        $this->appendAssessment($issues, $actions, $watch, $organicMatter);

        $moisture = $this->assessRange(
            'Soil moisture',
            $soilHealth->moisture_level,
            $cropProfile['moisture_min'],
            $cropProfile['moisture_max'],
            5,
            8,
            lowAction: 'Increase moisture steadily and use mulch or shorter irrigation intervals to reduce stress.',
            highAction: 'Improve drainage and shorten irrigation cycles before root stress increases.',
            goodNote: 'Moisture is currently in a workable range.',
        );
        $this->appendAssessment($issues, $actions, $watch, $moisture);

        $soilTypeAction = $this->soilTypeManagementAction($soilHealth->soil_type);
        if ($soilTypeAction !== null) {
            $watch[] = $soilTypeAction;
        }

        $trends = $this->buildTrends($soilHealth, $previous);
        foreach ($trends as $trend) {
            if (($trend['direction'] ?? '') === 'worsening') {
                $watch[] = (string) ($trend['message'] ?? '');
            }
        }

        $severityRank = ['critical' => 4, 'high' => 3, 'watch' => 2, 'good' => 1];
        usort($issues, static function (array $a, array $b) use ($severityRank): int {
            return ($severityRank[$b['severity']] ?? 0) <=> ($severityRank[$a['severity']] ?? 0);
        });

        $actions = array_values(array_unique(array_filter($actions)));
        $watch = array_values(array_unique(array_filter($watch)));

        $headline = 'Soil conditions are broadly stable.';
        $overall = 'stable';
        if (collect($issues)->contains(fn ($item) => ($item['severity'] ?? '') === 'critical')) {
            $overall = 'urgent';
            $headline = 'Soil conditions need urgent correction before the next field operation.';
        } elseif (collect($issues)->contains(fn ($item) => in_array(($item['severity'] ?? ''), ['high', 'watch'], true))) {
            $overall = 'attention';
            $headline = 'Soil conditions need targeted adjustment for better crop performance.';
        }

        if ($cropName !== '') {
            $headline .= " Active crop context: {$cropName}.";
        }

        return [
            'overall_status' => $overall,
            'headline' => $headline,
            'crop_context' => [
                'active_crop' => $cropName !== '' ? $cropName : null,
                'soil_type' => $soilHealth->soil_type,
                'review_status' => strtolower((string) $soilHealth->review_status),
            ],
            'issues' => $issues,
            'actions' => $actions,
            'watch_items' => $watch,
            'trends' => $trends,
            'next_steps' => $this->nextSteps($soilHealth, $overall),
        ];
    }

    protected function cropProfileFor(?string $cropName): array
    {
        $name = strtolower(trim((string) $cropName));
        $profiles = [
            'maize' => ['ph_min' => 5.8, 'ph_max' => 7.0, 'nitrogen_min' => 60, 'nitrogen_max' => 180, 'phosphorus_min' => 20, 'phosphorus_max' => 80, 'potassium_min' => 120, 'potassium_max' => 300, 'moisture_min' => 35, 'moisture_max' => 60],
            'tomato' => ['ph_min' => 5.8, 'ph_max' => 6.8, 'nitrogen_min' => 50, 'nitrogen_max' => 160, 'phosphorus_min' => 25, 'phosphorus_max' => 90, 'potassium_min' => 150, 'potassium_max' => 350, 'moisture_min' => 40, 'moisture_max' => 65],
            'potato' => ['ph_min' => 5.2, 'ph_max' => 6.5, 'nitrogen_min' => 45, 'nitrogen_max' => 150, 'phosphorus_min' => 20, 'phosphorus_max' => 80, 'potassium_min' => 160, 'potassium_max' => 360, 'moisture_min' => 45, 'moisture_max' => 70],
            'pepper' => ['ph_min' => 5.8, 'ph_max' => 6.8, 'nitrogen_min' => 45, 'nitrogen_max' => 140, 'phosphorus_min' => 20, 'phosphorus_max' => 80, 'potassium_min' => 140, 'potassium_max' => 320, 'moisture_min' => 40, 'moisture_max' => 65],
        ];

        return $profiles[$name] ?? ['ph_min' => 5.8, 'ph_max' => 7.0, 'nitrogen_min' => 50, 'nitrogen_max' => 170, 'phosphorus_min' => 20, 'phosphorus_max' => 80, 'potassium_min' => 120, 'potassium_max' => 320, 'moisture_min' => 35, 'moisture_max' => 65];
    }

    protected function assessRange(
        string $label,
        $rawValue,
        float $min,
        float $max,
        float $warningMarginLow,
        float $warningMarginHigh,
        string $lowAction,
        string $highAction,
        string $goodNote,
    ): ?array {
        if ($rawValue === null) {
            return null;
        }

        $value = (float) $rawValue;
        if ($value < $min - $warningMarginLow) {
            return ['metric' => $label, 'value' => $value, 'severity' => 'critical', 'message' => "{$label} is far below the target range.", 'action' => $lowAction];
        }
        if ($value < $min) {
            return ['metric' => $label, 'value' => $value, 'severity' => 'watch', 'message' => "{$label} is slightly below the target range.", 'action' => $lowAction];
        }
        if ($value > $max + $warningMarginHigh) {
            return ['metric' => $label, 'value' => $value, 'severity' => 'critical', 'message' => "{$label} is far above the target range.", 'action' => $highAction];
        }
        if ($value > $max) {
            return ['metric' => $label, 'value' => $value, 'severity' => 'watch', 'message' => "{$label} is slightly above the target range.", 'action' => $highAction];
        }

        return ['metric' => $label, 'value' => $value, 'severity' => 'good', 'message' => $goodNote, 'action' => null];
    }

    protected function appendAssessment(array &$issues, array &$actions, array &$watch, ?array $assessment): void
    {
        if ($assessment === null) {
            return;
        }

        $issues[] = $assessment;
        if (($assessment['severity'] ?? '') === 'good') {
            $watch[] = (string) $assessment['message'];
            return;
        }

        if (! empty($assessment['action'])) {
            $actions[] = (string) $assessment['action'];
        }
    }

    protected function soilTypeManagementAction(?string $soilType): ?string
    {
        return match (strtolower(trim((string) $soilType))) {
            'clay' => 'Clay soil context: keep drainage open and avoid heavy irrigation after rain.',
            'sandy' => 'Sandy soil context: split nutrient applications and protect moisture with mulch.',
            'silty' => 'Silty soil context: protect against crusting and compaction during cultivation.',
            'loam' => 'Loam soil context: maintain current structure with organic residue and balanced traffic.',
            default => null,
        };
    }

    protected function buildTrends(SoilHealth $current, ?SoilHealth $previous): array
    {
        if ($previous === null) {
            return [];
        }

        $trendSpecs = [
            ['key' => 'ph_level', 'label' => 'Soil pH', 'small' => 0.2, 'large' => 0.6, 'higher_is_better' => null],
            ['key' => 'organic_matter', 'label' => 'Organic matter', 'small' => 0.3, 'large' => 0.8, 'higher_is_better' => true],
            ['key' => 'moisture_level', 'label' => 'Soil moisture', 'small' => 5, 'large' => 12, 'higher_is_better' => null],
            ['key' => 'nitrogen', 'label' => 'Nitrogen', 'small' => 10, 'large' => 30, 'higher_is_better' => true],
            ['key' => 'phosphorus', 'label' => 'Phosphorus', 'small' => 5, 'large' => 15, 'higher_is_better' => true],
            ['key' => 'potassium', 'label' => 'Potassium', 'small' => 15, 'large' => 40, 'higher_is_better' => true],
        ];

        $items = [];
        foreach ($trendSpecs as $spec) {
            $key = $spec['key'];
            if ($current->{$key} === null || $previous->{$key} === null) {
                continue;
            }
            $delta = (float) $current->{$key} - (float) $previous->{$key};
            $abs = abs($delta);
            if ($abs < $spec['small']) {
                $items[] = ['metric' => $spec['label'], 'direction' => 'stable', 'delta' => round($delta, 2), 'message' => "{$spec['label']} is stable compared with the previous test."];
                continue;
            }

            $direction = $delta > 0 ? 'up' : 'down';
            $quality = 'changing';
            if ($spec['higher_is_better'] === true) {
                $quality = $delta > 0 ? 'improving' : 'worsening';
            }
            $message = "{$spec['label']} moved ".($delta > 0 ? 'up' : 'down')." since the previous test.";
            if ($abs >= $spec['large']) {
                $message = "{$spec['label']} changed sharply since the previous test.";
            }
            $items[] = ['metric' => $spec['label'], 'direction' => $quality === 'changing' ? $direction : $quality, 'delta' => round($delta, 2), 'message' => $message];
        }

        return $items;
    }

    protected function nextSteps(SoilHealth $soilHealth, string $overall): array
    {
        $steps = [];
        if ($overall === 'urgent') {
            $steps[] = 'Correct the highest-severity soil issue before the next major input or irrigation cycle.';
        }
        $steps[] = 'Retest this plot after the next management change to confirm the soil response.';
        if (strtolower((string) $soilHealth->review_status) !== 'validated') {
            $steps[] = 'Ask a supporter or expert to validate this soil record before relying on it for chemical input decisions.';
        }
        return $steps;
    }

    protected function validatePlotAccess($user, $plotId)
    {
        if (RegionScope::roleName($user) === 'farmer') {
            $plot = Plot::find($plotId);
            if (!$plot || $plot->farm->farmer_id !== $user->id) {
                abort(403, 'Access denied to this plot');
            }
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            $plot = Plot::whereHas('farm', fn ($farmQuery) => $farmQuery->whereIn('region_id', $regionIds))
                ->find($plotId);
            if (!$plot) {
                abort(403, 'Access denied to this plot');
            }
        }
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);
        return max(1, min($perPage, 100));
    }

    protected function evidenceDirectory(SoilHealth $soilHealth): string
    {
        return 'soil_evidence/' . $soilHealth->id;
    }

    protected function pruneEvidenceFiles(SoilHealth $soilHealth, string $keepPath): void
    {
        $disk = Storage::disk('public');
        foreach ($disk->files($this->evidenceDirectory($soilHealth)) as $path) {
            if ($path === $keepPath) {
                continue;
            }

            $disk->delete($path);
        }
    }

    protected function deleteEvidenceDirectory(SoilHealth $soilHealth): void
    {
        Storage::disk('public')->deleteDirectory($this->evidenceDirectory($soilHealth));
    }

    protected function normalizeJsonPayloadFields(Request $request, array $fields): void
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
