<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WeatherData;
use App\Models\Plot;
use App\Models\Farm;
use App\Models\Region;
use App\Services\InferencePipelineService;
use App\Support\ApiLocalizer;
use App\Support\RegionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class WeatherDataController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', WeatherData::class);

        $user = $request->user();
        $query = WeatherData::query();

        // Apply region scoping for non-farmers
        if (RegionScope::roleName($user) !== 'farmer') {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('region_id', $regionIds);
            }
        } else {
            // Farmers can only see their own data
            $query->whereHas('farm', fn ($farmQuery) => $farmQuery->where('farmer_id', $user->id));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            try {
                $query->whereBetween('recorded_at', [$startDate, $endDate]);
            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    'date_range' => ['Invalid date range provided.'],
                ]);
            }
        }

        // Filter by data source
        if ($request->has('data_source')) {
            $query->where('data_source', $request->input('data_source'));
        }

        // Filter by farm
        if ($request->has('farm_id')) {
            $farmId = $request->input('farm_id');
            $this->validateFarmAccess($user, $farmId);
            $query->where('farm_id', $farmId);
        }

        // Filter by plot
        if ($request->has('plot_id')) {
            $plotId = $request->input('plot_id');
            $this->validatePlotAccess($user, $plotId);
            $query->where('plot_id', $plotId);
        }

        $data = $query->orderBy('recorded_at', 'desc')->paginate($this->perPage($request));

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

    public function show(WeatherData $weatherData)
    {
        $this->authorize('view', $weatherData);
        return response()->json($weatherData);
    }

    public function store(Request $request)
    {
        $this->authorize('create', WeatherData::class);

        $this->normalizeJsonPayloadFields($request, ['sensor_payload', 'field_context']);

        $data = $request->validate([
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'plot_id' => ['nullable', 'integer', 'exists:plots,id'],
            'temperature' => ['nullable', 'numeric', 'min:-50', 'max:60'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'wind_speed' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'soil_moisture' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'data_source' => ['required', 'string', 'max:50'],
            'sensor_device_id' => ['nullable', 'string', 'max:120'],
            'sensor_reading_id' => ['nullable', 'string', 'max:160'],
            'sensor_payload' => ['nullable', 'array'],
            'field_context' => ['nullable', 'array'],
            'battery_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signal_quality' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        // Validate access to farm/plot if provided
        if (!empty($data['farm_id'])) {
            $this->validateFarmAccess($request->user(), $data['farm_id']);
        }
        if (!empty($data['plot_id'])) {
            $this->validatePlotAccess($request->user(), $data['plot_id']);
        }

        $weatherData = WeatherData::create([
            ...$data,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        return response()->json($weatherData, 201);
    }

    public function update(Request $request, WeatherData $weatherData)
    {
        $this->authorize('update', $weatherData);

        $this->normalizeJsonPayloadFields($request, ['sensor_payload', 'field_context']);

        $data = $request->validate([
            'temperature' => ['nullable', 'numeric', 'min:-50', 'max:60'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'wind_speed' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'soil_moisture' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'data_source' => ['string', 'max:50'],
            'sensor_device_id' => ['nullable', 'string', 'max:120'],
            'sensor_reading_id' => ['nullable', 'string', 'max:160'],
            'sensor_payload' => ['nullable', 'array'],
            'field_context' => ['nullable', 'array'],
            'battery_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signal_quality' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $weatherData->update($data);
        return response()->json($weatherData);
    }

    public function destroy(Request $request, WeatherData $weatherData)
    {
        $this->authorize('delete', $weatherData);
        $weatherData->delete();
        return response()->json(['message' => ApiLocalizer::message($request, 'weather_deleted')]);
    }

    public function summary(Request $request)
    {
        $this->authorize('viewAny', WeatherData::class);

        $user = $request->user();
        $query = WeatherData::query();

        // Apply region scoping
        if (RegionScope::roleName($user) !== 'farmer') {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                return response()->json(['summary' => []]);
            }
            $query->whereIn('region_id', $regionIds);
        } else {
            $query->whereHas('farm', fn ($farmQuery) => $farmQuery->where('farmer_id', $user->id));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $query->whereBetween('recorded_at', [$startDate, $endDate]);
        }

        $summary = $query->selectRaw('
            AVG(temperature) as avg_temperature,
            MIN(temperature) as min_temperature,
            MAX(temperature) as max_temperature,
            AVG(humidity) as avg_humidity,
            SUM(precipitation) as total_precipitation,
            AVG(wind_speed) as avg_wind_speed,
            AVG(soil_moisture) as avg_soil_moisture,
            COUNT(*) as total_records,
            MAX(recorded_at) as latest_recorded_at
        ')->first();

        return response()->json([
            'summary' => $summary,
            'analysis' => ApiLocalizer::localizeWeatherAnalysis(
                $request,
                $this->buildSummaryAnalysis($summary)
            ),
        ]);
    }

    protected function buildSummaryAnalysis($summary): array
    {
        $avgTemperature = $summary?->avg_temperature !== null ? (float) $summary->avg_temperature : null;
        $avgHumidity = $summary?->avg_humidity !== null ? (float) $summary->avg_humidity : null;
        $totalPrecipitation = $summary?->total_precipitation !== null ? (float) $summary->total_precipitation : null;
        $avgWind = $summary?->avg_wind_speed !== null ? (float) $summary->avg_wind_speed : null;
        $avgSoilMoisture = $summary?->avg_soil_moisture !== null ? (float) $summary->avg_soil_moisture : null;
        $totalRecords = $summary?->total_records !== null ? (int) $summary->total_records : 0;

        $riskLevel = 'low';
        $headline = 'Weather conditions are currently stable for routine field work.';
        $watchItems = [];
        $actions = [];

        if (($avgHumidity ?? 0) >= 82 || ($totalPrecipitation ?? 0) >= 45) {
            $riskLevel = 'high';
            $headline = 'Wet weather conditions are increasing disease and field-access risk.';
            $watchItems[] = 'Monitor low-lying plots for standing water and prolonged leaf wetness.';
            $actions[] = 'Prioritize drainage checks and avoid unnecessary overhead irrigation.';
        } elseif (($avgTemperature ?? 0) >= 32) {
            $riskLevel = 'moderate';
            $headline = 'Heat is the main weather concern and can stress crops quickly.';
            $watchItems[] = 'Look for wilting and fast soil moisture decline during the hottest hours.';
            $actions[] = 'Shift irrigation earlier in the day and protect exposed seedlings if possible.';
        } elseif ($avgSoilMoisture !== null && $avgSoilMoisture < 30) {
            $riskLevel = 'moderate';
            $headline = 'Dry field conditions are becoming the main production risk.';
            $watchItems[] = 'Track weaker plots for moisture stress and uneven crop development.';
            $actions[] = 'Review irrigation timing and preserve soil cover to slow moisture loss.';
        } else {
            $watchItems[] = 'Continue regular field checks and note any sudden weather changes.';
            $actions[] = 'Use the current weather window for routine scouting, weeding, and planning.';
        }

        if (($avgWind ?? 0) >= 25) {
            $watchItems[] = 'Stronger winds may increase lodging risk and dry the canopy faster.';
        }

        return [
            'risk_level' => $riskLevel,
            'headline' => $headline,
            'watch_items' => array_values(array_unique($watchItems)),
            'actions' => array_values(array_unique($actions)),
            'total_records' => $totalRecords,
            'latest_recorded_at' => $summary?->latest_recorded_at,
        ];
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

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);
        return max(1, min($perPage, 100));
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
