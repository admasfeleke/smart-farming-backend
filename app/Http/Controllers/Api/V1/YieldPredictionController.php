<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Planting;
use App\Models\Farm;
use App\Models\Plot;
use App\Services\CropYieldPredictionService;
use App\Support\ApiLocalizer;
use App\Support\RegionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class YieldPredictionController extends Controller
{
    protected $yieldPredictionService;

    public function __construct(CropYieldPredictionService $yieldPredictionService)
    {
        $this->yieldPredictionService = $yieldPredictionService;
    }

    public function predict(Request $request)
    {
        $data = $request->validate([
            'planting_id' => ['required', 'integer', 'exists:plantings,id'],
            'temperature' => ['nullable', 'numeric', 'min:-50', 'max:60'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'soil_ph' => ['nullable', 'numeric', 'min:3', 'max:10'],
            'soil_nitrogen' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'soil_phosphorus' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'soil_potassium' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'soil_moisture' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $planting = Planting::find($data['planting_id']);

        // Validate access to planting
        $this->validatePlantingAccess($request->user(), $planting);

        // Prepare current conditions
        $currentConditions = [];
        if (array_key_exists('temperature', $data) && $data['temperature'] !== null) {
            $currentConditions['temperature'] = $data['temperature'];
        }
        if (array_key_exists('humidity', $data) && $data['humidity'] !== null) {
            $currentConditions['humidity'] = $data['humidity'];
        }
        if (array_key_exists('precipitation', $data) && $data['precipitation'] !== null) {
            $currentConditions['precipitation'] = $data['precipitation'];
        }
        if (array_key_exists('soil_ph', $data) && $data['soil_ph'] !== null) {
            $currentConditions['soil_ph'] = $data['soil_ph'];
        }
        if (array_key_exists('soil_nitrogen', $data) && $data['soil_nitrogen'] !== null) {
            $currentConditions['soil_nitrogen'] = $data['soil_nitrogen'];
        }
        if (array_key_exists('soil_phosphorus', $data) && $data['soil_phosphorus'] !== null) {
            $currentConditions['soil_phosphorus'] = $data['soil_phosphorus'];
        }
        if (array_key_exists('soil_potassium', $data) && $data['soil_potassium'] !== null) {
            $currentConditions['soil_potassium'] = $data['soil_potassium'];
        }
        if (array_key_exists('soil_moisture', $data) && $data['soil_moisture'] !== null) {
            $currentConditions['soil_moisture'] = $data['soil_moisture'];
        }

        $prediction = $this->yieldPredictionService->predictYield($planting, $currentConditions);
        $prediction['crop_name'] = (string) ($planting->crop?->name ?? '');
        $prediction = ApiLocalizer::localizeYieldPrediction($request, $prediction);

        return response()->json([
            'planting' => $planting,
            'prediction' => $prediction,
        ]);
    }

    public function show(Request $request, Planting $planting)
    {
        // Validate access to planting
        $this->validatePlantingAccess($request->user(), $planting);

        $prediction = $this->yieldPredictionService->predictYield($planting);
        $prediction['crop_name'] = (string) ($planting->crop?->name ?? '');
        $prediction = ApiLocalizer::localizeYieldPrediction($request, $prediction);

        return response()->json([
            'planting' => $planting,
            'prediction' => $prediction,
        ]);
    }

    protected function validatePlantingAccess($user, Planting $planting)
    {
        if (RegionScope::roleName($user) === 'farmer') {
            if ($planting->plot->farm->farmer_id !== $user->id) {
                abort(403, 'Access denied to this planting');
            }
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            $plantingExists = Planting::whereIn('plot_id', function ($subQuery) use ($regionIds) {
                $subQuery->select('id')
                    ->from('plots')
                    ->whereIn('farm_id', function ($farmQuery) use ($regionIds) {
                        $farmQuery->select('id')
                            ->from('farms')
                            ->whereIn('region_id', $regionIds);
                    });
            })->where('id', $planting->id)->exists();

            if (!$plantingExists) {
                abort(403, 'Access denied to this planting');
            }
        }
    }
}
