<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\Plot;
use App\Services\DiseasePreventionService;
use App\Support\ApiLocalizer;
use App\Support\RegionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DiseasePreventionController extends Controller
{
    protected $diseasePreventionService;

    public function __construct(DiseasePreventionService $diseasePreventionService)
    {
        $this->diseasePreventionService = $diseasePreventionService;
    }

    public function analyze(Request $request)
    {
        $data = $request->validate([
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'plot_id' => ['nullable', 'integer', 'exists:plots,id'],
            'crop_id' => ['nullable', 'integer', 'exists:crops,id'],
            'temperature' => ['nullable', 'numeric', 'min:-50', 'max:60'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'soil_moisture' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Validate access to farm/plot if provided
        if (!empty($data['farm_id'])) {
            $this->validateFarmAccess($request->user(), $data['farm_id']);
        }
        if (!empty($data['plot_id'])) {
            $this->validatePlotAccess($request->user(), $data['plot_id']);
        }

        $conditions = $this->extractConditions($data);

        $result = $this->diseasePreventionService->analyzeAndGeneratePreventiveAlerts(
            farmId: $data['farm_id'] ?? null,
            plotId: $data['plot_id'] ?? null,
            cropId: $data['crop_id'] ?? null,
            conditions: $conditions,
        );

        return response()->json([
            'message' => ApiLocalizer::message($request, 'disease_prevention_completed'),
            'scope' => [
                'farm_id' => $data['farm_id'] ?? null,
                'plot_id' => $data['plot_id'] ?? null,
                'crop_id' => $data['crop_id'] ?? null,
            ],
            'conditions' => $conditions,
            'result' => $result,
        ]);
    }

    public function getRecommendations(Request $request)
    {
        $data = $request->validate([
            'crop_id' => ['required', 'integer', 'exists:crops,id'],
            'temperature' => ['nullable', 'numeric', 'min:-50', 'max:60'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'soil_moisture' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $crop = Crop::find($data['crop_id']);

        $conditions = $this->extractConditions($data);

        $analysis = $this->diseasePreventionService->getPreventiveRecommendationsForCrop(
            $crop->id,
            $conditions
        );
        $analysis = ApiLocalizer::localizeDiseasePrevention($request, $analysis);

        return response()->json([
            'crop' => $crop,
            'conditions' => $conditions,
            'analysis' => $analysis,
            'recommendations' => $analysis['recommendations'] ?? [],
        ]);
    }

    protected function validateFarmAccess($user, $farmId)
    {
        if (RegionScope::roleName($user) === 'farmer') {
            $farm = Farm::find($farmId);
            if (!$farm || $farm->farmer_id !== $user->id) {
                abort(403, 'Access denied to this farm');
            }
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            $farm = Farm::whereIn('region_id', $regionIds)->find($farmId);
            if (!$farm) {
                abort(403, 'Access denied to this farm');
            }
        }
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

    private function extractConditions(array $data): array
    {
        $conditions = [];

        foreach (['temperature', 'humidity', 'precipitation', 'soil_moisture'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $conditions[$key] = $data[$key];
            }
        }

        return $conditions;
    }
}
