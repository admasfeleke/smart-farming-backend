<?php

namespace App\Services;

use App\Models\Plot;
use App\Models\WeatherData;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class WeatherContextService
{
    private const SENSOR_SOURCES = ['sensor', 'iot', 'sensor_iot', 'weather_station'];
    private const MANUAL_SOURCES = ['manual', 'farmer', 'field', 'human'];
    private const FORECAST_SOURCES = ['forecast', 'weather_forecast', 'external_forecast', 'api_forecast'];

    public function buildForPlot(Plot $plot, array $overrideConditions = [], int $lookbackDays = 30): array
    {
        $weatherRecords = WeatherData::forPlot($plot->id)
            ->where('recorded_at', '>=', now()->subDays($lookbackDays))
            ->orderByDesc('recorded_at')
            ->get();

        $sensorRecords = $this->filterBySource($weatherRecords, self::SENSOR_SOURCES);
        $manualRecords = $this->filterBySource($weatherRecords, self::MANUAL_SOURCES);
        $forecastRecords = $this->filterBySource($weatherRecords, self::FORECAST_SOURCES);

        $observedRecords = $sensorRecords->isNotEmpty() ? $sensorRecords : $manualRecords;
        if ($observedRecords->isEmpty()) {
            $observedRecords = $weatherRecords;
        }

        $observed = $this->buildObservedSnapshot($observedRecords, $overrideConditions, $plot);
        $forecast = $this->buildForecastSnapshot($forecastRecords);

        return [
            'plot_id' => $plot->id,
            'farm_id' => $plot->farm_id,
            'lookback_days' => $lookbackDays,
            'soil_type' => $plot->soil_type,
            'total_records' => $weatherRecords->count(),
            'source_breakdown' => [
                'sensor' => $this->summarizeSource($sensorRecords),
                'manual' => $this->summarizeSource($manualRecords),
                'forecast' => $this->summarizeSource($forecastRecords),
            ],
            'observed' => $observed,
            'forecast' => $forecast,
            'combined' => $this->buildCombinedContext($observed, $forecast, $overrideConditions, $plot),
        ];
    }

    protected function buildObservedSnapshot(Collection $records, array $overrideConditions, Plot $plot): array
    {
        $latest = $records->first();
        $averages = $this->averageMetrics($records);
        $latestSource = $this->normalizeSource($latest?->data_source);

        $observed = [
            'temperature' => $averages['temperature'] ?? null,
            'humidity' => $averages['humidity'] ?? null,
            'precipitation' => $averages['precipitation'] ?? null,
            'wind_speed' => $averages['wind_speed'] ?? null,
            'soil_moisture' => $averages['soil_moisture'] ?? null,
            'recorded_at' => $this->latestRecordedAt($records),
            'source' => $latestSource,
            'sensor_device_id' => $latest?->sensor_device_id,
            'field_context' => $latest?->field_context ?? [],
            'soil_type' => $this->fieldContextValue($latest?->field_context, 'soil_type') ?: $plot->soil_type,
        ];

        foreach (['temperature', 'humidity', 'precipitation', 'wind_speed', 'soil_moisture'] as $metric) {
            if (array_key_exists($metric, $overrideConditions) && $overrideConditions[$metric] !== null) {
                $observed[$metric] = $overrideConditions[$metric];
            }
        }

        return $observed;
    }

    protected function buildForecastSnapshot(Collection $forecastRecords): array
    {
        $latest = $forecastRecords->first();

        if ($latest === null) {
            return [
                'available' => false,
                'temperature' => null,
                'humidity' => null,
                'precipitation' => null,
                'wind_speed' => null,
                'soil_moisture' => null,
                'recorded_at' => null,
                'source' => null,
            ];
        }

        return [
            'available' => true,
            'temperature' => $latest->temperature,
            'humidity' => $latest->humidity,
            'precipitation' => $latest->precipitation,
            'wind_speed' => $latest->wind_speed,
            'soil_moisture' => $latest->soil_moisture,
            'recorded_at' => optional($latest->recorded_at)->toIso8601String(),
            'source' => $this->normalizeSource($latest->data_source),
            'field_context' => $latest->field_context ?? [],
            'sensor_payload' => $latest->sensor_payload ?? [],
        ];
    }

    protected function buildCombinedContext(array $observed, array $forecast, array $overrideConditions, Plot $plot): array
    {
        $combined = [
            'temperature' => $observed['temperature'] ?? null,
            'humidity' => $observed['humidity'] ?? null,
            'precipitation' => $observed['precipitation'] ?? null,
            'wind_speed' => $observed['wind_speed'] ?? null,
            'soil_moisture' => $observed['soil_moisture'] ?? null,
            'soil_type' => $observed['soil_type'] ?? $plot->soil_type,
            'recorded_at' => $observed['recorded_at'] ?? null,
            'source' => $observed['source'] ?? null,
            'forecast_available' => (bool) ($forecast['available'] ?? false),
            'forecast_temperature' => $forecast['temperature'] ?? null,
            'forecast_humidity' => $forecast['humidity'] ?? null,
            'forecast_precipitation' => $forecast['precipitation'] ?? null,
            'forecast_wind_speed' => $forecast['wind_speed'] ?? null,
            'forecast_recorded_at' => $forecast['recorded_at'] ?? null,
            'risk_signals' => [],
        ];

        if (($forecast['available'] ?? false) === true) {
            $combined['risk_signals'] = $this->buildRiskSignals($observed, $forecast);
        }

        foreach ($overrideConditions as $key => $value) {
            if ($value !== null) {
                $combined[$key] = $value;
            }
        }

        return $combined;
    }

    protected function summarizeSource(Collection $records): array
    {
        $latest = $records->first();
        $counts = $records->countBy(fn ($record) => $this->normalizeSource($record->data_source));

        return [
            'records' => $records->count(),
            'latest_recorded_at' => $this->latestRecordedAt($records),
            'latest_source' => $this->normalizeSource($latest?->data_source),
            'counts' => $counts->all(),
        ];
    }

    protected function averageMetrics(Collection $records): array
    {
        $values = [
            'temperature' => [],
            'humidity' => [],
            'precipitation' => [],
            'wind_speed' => [],
            'soil_moisture' => [],
        ];

        foreach ($records as $record) {
            foreach (array_keys($values) as $metric) {
                if ($record->{$metric} !== null) {
                    $values[$metric][] = (float) $record->{$metric};
                }
            }
        }

        return array_map(
            fn (array $items) => $items === [] ? null : round(array_sum($items) / count($items), 2),
            $values
        );
    }

    protected function buildRiskSignals(array $observed, array $forecast): array
    {
        $signals = [];

        $observedHumidity = $this->toFloat($observed['humidity'] ?? null);
        $observedTemperature = $this->toFloat($observed['temperature'] ?? null);
        $observedRain = $this->toFloat($observed['precipitation'] ?? null);
        $observedMoisture = $this->toFloat($observed['soil_moisture'] ?? null);
        $forecastHumidity = $this->toFloat($forecast['humidity'] ?? null);
        $forecastRain = $this->toFloat($forecast['precipitation'] ?? null);
        $forecastTemperature = $this->toFloat($forecast['temperature'] ?? null);

        if (($observedHumidity !== null && $observedHumidity >= 82) || ($forecastHumidity !== null && $forecastHumidity >= 82)) {
            $signals[] = 'high_humidity';
        }
        if (($observedRain !== null && $observedRain >= 20) || ($forecastRain !== null && $forecastRain >= 20)) {
            $signals[] = 'rain_pressure';
        }
        if (($observedMoisture !== null && $observedMoisture >= 75) || ($forecastRain !== null && $forecastRain >= 20)) {
            $signals[] = 'wet_root_zone';
        }
        if (($observedTemperature !== null && $observedTemperature >= 32) || ($forecastTemperature !== null && $forecastTemperature >= 32)) {
            $signals[] = 'heat_pressure';
        }

        return array_values(array_unique($signals));
    }

    protected function filterBySource(Collection $records, array $sources): Collection
    {
        return $records->filter(function ($record) use ($sources) {
            $source = $this->normalizeSource($record->data_source);

            foreach ($sources as $candidate) {
                if ($source === $candidate || str_contains($source, $candidate)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    protected function normalizeSource(?string $source): string
    {
        return strtolower(trim((string) $source));
    }

    protected function latestRecordedAt(Collection $records): ?string
    {
        $latest = $records->first();

        if (! $latest) {
            return null;
        }

        $recordedAt = $latest->recorded_at;
        if ($recordedAt instanceof Carbon) {
            return $recordedAt->toIso8601String();
        }

        if (is_string($recordedAt) && trim($recordedAt) !== '') {
            try {
                return Carbon::parse($recordedAt)->toIso8601String();
            } catch (\Throwable) {
                return optional($latest->created_at)->toIso8601String();
            }
        }

        return optional($latest->created_at)->toIso8601String();
    }

    protected function fieldContextValue(?array $context, string $key): ?string
    {
        $value = data_get($context ?? [], $key);
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
