<?php

namespace App\Services;

use App\Models\Crop;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\SoilHealth;
use App\Models\WeatherData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CropYieldPredictionService
{
    /**
     * Predict crop yield using crop history, current soil/weather context,
     * and the planting's current progress toward harvest.
     */
    public function predictYield(Planting $planting, array $currentConditions = []): array
    {
        $planting->loadMissing(['crop', 'plot.farm']);

        $crop = $planting->crop;
        $plot = $planting->plot;
        $cacheKey = $this->getCacheKey($planting, $currentConditions);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $historicalData = $this->getHistoricalYieldData($crop, $plot);
        $environmentalFactors = $this->getCurrentEnvironmentalFactors($plot, $currentConditions);
        $growthContext = $this->buildGrowthContext($planting);
        $baseYield = $this->calculateBaseYield($crop, $historicalData);
        $adjustedYield = $this->applyEnvironmentalAdjustments(
            $baseYield,
            $environmentalFactors,
            $growthContext,
            $crop
        );
        $riskFlags = $this->buildRiskFlags($environmentalFactors, $growthContext);
        $yieldBand = $this->buildYieldBand($baseYield, $adjustedYield);
        $confidence = $this->calculateConfidence(
            $historicalData,
            $environmentalFactors,
            $growthContext
        );
        $recommendations = $this->generateRecommendations(
            $environmentalFactors,
            $adjustedYield,
            $growthContext,
            $riskFlags
        );

        $result = [
            'headline' => $this->buildHeadline($crop, $adjustedYield, $yieldBand, $riskFlags),
            'predicted_yield' => round($adjustedYield, 2),
            'baseline_yield' => round($baseYield, 2),
            'yield_band' => $yieldBand,
            'confidence_interval' => [
                'lower' => round($adjustedYield * (1 - $confidence), 2),
                'upper' => round($adjustedYield * (1 + $confidence), 2),
            ],
            'confidence_level' => round($confidence * 100, 1),
            'growth_context' => $growthContext,
            'risk_flags' => $riskFlags,
            'factors' => $environmentalFactors,
            'recommendations' => $recommendations,
            'prediction_date' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, now()->addHours(24));

        return $result;
    }

    protected function getHistoricalYieldData(Crop $crop, Plot $plot): array
    {
        $previousPlantings = Planting::with('crop')
            ->where('crop_id', $crop->id)
            ->where('plot_id', $plot->id)
            ->where('status', 'harvested')
            ->whereNotNull('expected_harvest_date')
            ->orderBy('expected_harvest_date', 'desc')
            ->limit(5)
            ->get();

        $historicalData = [];

        foreach ($previousPlantings as $planting) {
            $estimatedYield = $this->estimateYieldFromPlanting($planting);
            $plantingDate = $this->asDate($planting->planting_date);
            $expectedHarvestDate = $this->asDate($planting->expected_harvest_date);
            $growingDays = $plantingDate && $expectedHarvestDate
                ? $plantingDate->diffInDays($expectedHarvestDate)
                : null;

            $historicalData[] = [
                'planting_date' => optional($plantingDate)->toDateString(),
                'harvest_date' => optional($expectedHarvestDate)->toDateString(),
                'estimated_yield' => $estimatedYield,
                'growing_days' => $growingDays,
            ];
        }

        return $historicalData;
    }

    protected function getCurrentEnvironmentalFactors(Plot $plot, array $currentConditions): array
    {
        $factors = [
            'temperature' => null,
            'humidity' => null,
            'precipitation' => null,
            'soil_ph' => null,
            'soil_nutrients' => null,
            'soil_moisture' => null,
            'soil_type' => $plot->soil_type,
            'weather_observed_at' => null,
            'soil_test_date' => null,
        ];

        $recentWeather = WeatherData::forPlot($plot->id)
            ->recent()
            ->latest()
            ->first();

        if ($recentWeather) {
            $factors['temperature'] = $recentWeather->temperature;
            $factors['humidity'] = $recentWeather->humidity;
            $factors['precipitation'] = $recentWeather->precipitation;
            $factors['soil_moisture'] = $recentWeather->soil_moisture;
            $factors['weather_observed_at'] = optional($recentWeather->recorded_at)->toIso8601String()
                ?? optional($recentWeather->created_at)->toIso8601String();
        }

        $soilHealth = SoilHealth::forPlot($plot->id)
            ->recent()
            ->latest('test_date')
            ->first();

        if ($soilHealth) {
            $factors['soil_ph'] = $soilHealth->ph_level;
            $factors['soil_nutrients'] = [
                'nitrogen' => $soilHealth->nitrogen,
                'phosphorus' => $soilHealth->phosphorus,
                'potassium' => $soilHealth->potassium,
                'organic_matter' => $soilHealth->organic_matter,
            ];
            $factors['soil_moisture'] = $factors['soil_moisture'] ?? $soilHealth->moisture_level;
            $factors['soil_type'] = $soilHealth->soil_type ?: $factors['soil_type'];
            $factors['soil_test_date'] = optional($soilHealth->test_date)->toDateString();
        }

        foreach ($currentConditions as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (in_array($key, ['soil_nitrogen', 'soil_phosphorus', 'soil_potassium'], true)) {
                $factors['soil_nutrients'] ??= [
                    'nitrogen' => null,
                    'phosphorus' => null,
                    'potassium' => null,
                    'organic_matter' => null,
                ];

                if ($key === 'soil_nitrogen') {
                    $factors['soil_nutrients']['nitrogen'] = $value;
                } elseif ($key === 'soil_phosphorus') {
                    $factors['soil_nutrients']['phosphorus'] = $value;
                } elseif ($key === 'soil_potassium') {
                    $factors['soil_nutrients']['potassium'] = $value;
                }

                continue;
            }

            if (array_key_exists($key, $factors)) {
                $factors[$key] = $value;
            }
        }

        return $factors;
    }

    protected function calculateBaseYield(Crop $crop, array $historicalData): float
    {
        if (empty($historicalData)) {
            return $this->getDefaultYieldForCrop($crop);
        }

        $totalYield = array_sum(array_column($historicalData, 'estimated_yield'));
        return $totalYield / count($historicalData);
    }

    protected function applyEnvironmentalAdjustments(
        float $baseYield,
        array $factors,
        array $growthContext,
        Crop $crop
    ): float {
        $adjustmentFactor = 1.0;

        if ($factors['temperature'] !== null) {
            $temp = (float) $factors['temperature'];
            if ($temp < 15 || $temp > 35) {
                $adjustmentFactor *= 0.78;
            } elseif ($temp < 18 || $temp > 32) {
                $adjustmentFactor *= 0.9;
            } elseif ($temp >= 22 && $temp <= 28) {
                $adjustmentFactor *= 1.04;
            }
        }

        if ($factors['soil_ph'] !== null) {
            $ph = (float) $factors['soil_ph'];
            if ($ph < 5.5 || $ph > 7.8) {
                $adjustmentFactor *= 0.84;
            } elseif ($ph >= 6.0 && $ph <= 7.0) {
                $adjustmentFactor *= 1.03;
            }
        }

        if ($factors['soil_moisture'] !== null) {
            $moisture = (float) $factors['soil_moisture'];
            if ($moisture < 20) {
                $adjustmentFactor *= 0.72;
            } elseif ($moisture < 35) {
                $adjustmentFactor *= 0.9;
            } elseif ($moisture > 80) {
                $adjustmentFactor *= 0.82;
            }
        }

        if (is_array($factors['soil_nutrients'])) {
            $nitrogen = $factors['soil_nutrients']['nitrogen'] ?? null;
            $phosphorus = $factors['soil_nutrients']['phosphorus'] ?? null;
            $potassium = $factors['soil_nutrients']['potassium'] ?? null;
            $organicMatter = $factors['soil_nutrients']['organic_matter'] ?? null;

            if ($nitrogen !== null && (float) $nitrogen < 35) {
                $adjustmentFactor *= 0.88;
            }
            if ($phosphorus !== null && (float) $phosphorus < 18) {
                $adjustmentFactor *= 0.92;
            }
            if ($potassium !== null && (float) $potassium < 90) {
                $adjustmentFactor *= 0.92;
            }
            if ($organicMatter !== null && (float) $organicMatter < 2.0) {
                $adjustmentFactor *= 0.94;
            }
        }

        $progress = $growthContext['progress_percent'] ?? null;
        if (is_numeric($progress)) {
            $progress = (float) $progress;
            if ($progress < 20) {
                $adjustmentFactor *= 0.97;
            } elseif ($progress >= 55 && $progress <= 85) {
                $adjustmentFactor *= 1.02;
            } elseif ($progress > 100) {
                $adjustmentFactor *= 0.95;
            }
        }

        $status = strtolower((string) ($growthContext['status'] ?? ''));
        if ($status === 'completed' || $status === 'harvested') {
            $adjustmentFactor *= 0.98;
        } elseif ($status === 'failed') {
            $adjustmentFactor *= 0.65;
        }

        $cropType = strtolower((string) $crop->crop_type);
        if ($cropType === 'vegetable' && ($factors['humidity'] ?? 0) > 85) {
            $adjustmentFactor *= 0.95;
        }

        return max($baseYield * $adjustmentFactor, 0);
    }

    protected function calculateConfidence(
        array $historicalData,
        array $factors,
        array $growthContext
    ): float {
        $confidence = 0.32;

        $historyCount = count($historicalData);
        if ($historyCount >= 3) {
            $confidence += 0.24;
        } elseif ($historyCount >= 1) {
            $confidence += 0.12;
        }

        $dataPoints = 0;
        $requiredPoints = 7;

        if ($factors['temperature'] !== null) {
            $dataPoints++;
        }
        if ($factors['humidity'] !== null) {
            $dataPoints++;
        }
        if ($factors['precipitation'] !== null) {
            $dataPoints++;
        }
        if ($factors['soil_ph'] !== null) {
            $dataPoints++;
        }
        if ($factors['soil_moisture'] !== null) {
            $dataPoints++;
        }
        if ($factors['soil_nutrients'] !== null) {
            $dataPoints++;
        }
        if (!empty($growthContext['expected_harvest_date'])) {
            $dataPoints++;
        }

        $confidence += ($dataPoints / $requiredPoints) * 0.32;

        $progress = $growthContext['progress_percent'] ?? null;
        if (is_numeric($progress) && (float) $progress >= 20) {
            $confidence += 0.07;
        }

        return min($confidence, 0.95);
    }

    protected function generateRecommendations(
        array $factors,
        float $predictedYield,
        array $growthContext,
        array $riskFlags
    ): array {
        $recommendations = [];

        if (($riskFlags['water_stress'] ?? false) === true) {
            $recommendations[] =
                'Prioritize irrigation scheduling and mulching because the crop is under moisture stress.';
        }

        if (($riskFlags['heat_stress'] ?? false) === true) {
            $recommendations[] =
                'Reduce midday heat stress with shade support where practical and maintain steady watering.';
        }

        if (($riskFlags['nutrient_gap'] ?? false) === true) {
            $recommendations[] =
                'Review the latest soil test and correct the weakest nutrient before the crop reaches final grain or fruit fill.';
        }

        if (($riskFlags['late_cycle'] ?? false) === true) {
            $recommendations[] =
                'The planting is near or beyond the expected harvest window; monitor maturity closely and plan harvesting logistics now.';
        }

        if ($factors['soil_ph'] !== null) {
            $ph = (float) $factors['soil_ph'];
            if ($ph < 5.5) {
                $recommendations[] = 'Apply lime gradually to move soil pH toward a more productive range.';
            } elseif ($ph > 7.8) {
                $recommendations[] =
                    'Incorporate organic matter and review alkaline-soil amendments to improve nutrient uptake.';
            }
        }

        if (($growthContext['stage_key'] ?? '') === 'early_establishment') {
            $recommendations[] =
                'Protect establishment with consistent moisture and early weed control to preserve yield potential.';
        } elseif (($growthContext['stage_key'] ?? '') === 'reproductive') {
            $recommendations[] =
                'Keep moisture and nutrient supply steady during flowering and fruit or grain set because this stage drives final yield.';
        }

        if ($predictedYield < 1000) {
            $recommendations[] =
                'Investigate agronomic constraints early; the current projection suggests the plot is underperforming against baseline potential.';
        }

        return array_values(array_unique($recommendations));
    }

    protected function getDefaultYieldForCrop(Crop $crop): float
    {
        $defaults = [
            'cereal' => 4000,
            'legume' => 2000,
            'vegetable' => 15000,
            'fruit' => 10000,
            'cash_crop' => 3000,
        ];

        return $defaults[$crop->crop_type] ?? 2500;
    }

    protected function estimateYieldFromPlanting(Planting $planting): float
    {
        return $this->getDefaultYieldForCrop($planting->crop) * 0.8;
    }

    protected function getCacheKey(Planting $planting, array $currentConditions): string
    {
        $conditionsHash = md5(serialize($currentConditions));
        return "yield_prediction_{$planting->id}_{$conditionsHash}";
    }

    protected function buildGrowthContext(Planting $planting): array
    {
        $plantingDate = $this->asDate($planting->planting_date);
        $expectedHarvestDate = $this->asDate($planting->expected_harvest_date);
        $daysSincePlanting = $plantingDate ? $plantingDate->diffInDays(now()) : null;
        $expectedGrowingDays = ($plantingDate && $expectedHarvestDate)
            ? $plantingDate->diffInDays($expectedHarvestDate)
            : null;

        $progressPercent = null;
        if ($daysSincePlanting !== null && $expectedGrowingDays && $expectedGrowingDays > 0) {
            $progressPercent = min(round(($daysSincePlanting / $expectedGrowingDays) * 100, 1), 150);
        }

        $stageKey = 'unknown';
        $stageLabel = 'Growth stage not available';
        if ($progressPercent !== null) {
            if ($progressPercent < 20) {
                $stageKey = 'early_establishment';
                $stageLabel = 'Early establishment';
            } elseif ($progressPercent < 55) {
                $stageKey = 'vegetative_growth';
                $stageLabel = 'Vegetative growth';
            } elseif ($progressPercent < 85) {
                $stageKey = 'reproductive';
                $stageLabel = 'Flowering / fruit or grain formation';
            } elseif ($progressPercent <= 105) {
                $stageKey = 'maturation';
                $stageLabel = 'Maturation';
            } else {
                $stageKey = 'past_expected_harvest';
                $stageLabel = 'Past expected harvest window';
            }
        }

        return [
            'status' => $planting->status,
            'planting_date' => optional($plantingDate)->toDateString(),
            'expected_harvest_date' => optional($expectedHarvestDate)->toDateString(),
            'days_since_planting' => $daysSincePlanting,
            'expected_growing_days' => $expectedGrowingDays,
            'progress_percent' => $progressPercent,
            'stage_key' => $stageKey,
            'stage_label' => $stageLabel,
        ];
    }

    protected function asDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildRiskFlags(array $factors, array $growthContext): array
    {
        $nutrients = is_array($factors['soil_nutrients'] ?? null) ? $factors['soil_nutrients'] : [];
        $progress = $growthContext['progress_percent'] ?? null;
        $temperature = $factors['temperature'] !== null ? (float) $factors['temperature'] : null;
        $soilMoisture = $factors['soil_moisture'] !== null ? (float) $factors['soil_moisture'] : null;
        $soilPh = $factors['soil_ph'] !== null ? (float) $factors['soil_ph'] : null;

        return [
            'water_stress' => $soilMoisture !== null && $soilMoisture < 30,
            'heat_stress' => $temperature !== null && $temperature > 33,
            'ph_stress' => $soilPh !== null && ($soilPh < 5.5 || $soilPh > 7.8),
            'nutrient_gap' =>
                (($nutrients['nitrogen'] ?? 9999) < 35)
                || (($nutrients['phosphorus'] ?? 9999) < 18)
                || (($nutrients['potassium'] ?? 9999) < 90),
            'late_cycle' => is_numeric($progress) && (float) $progress > 100,
        ];
    }

    protected function buildYieldBand(float $baseYield, float $adjustedYield): array
    {
        if ($baseYield <= 0) {
            return [
                'key' => 'unknown',
                'label' => 'Baseline not available',
                'delta_percent' => null,
            ];
        }

        $deltaPercent = round((($adjustedYield - $baseYield) / $baseYield) * 100, 1);

        if ($deltaPercent >= 8) {
            $key = 'above_baseline';
            $label = 'Above baseline';
        } elseif ($deltaPercent <= -12) {
            $key = 'below_baseline';
            $label = 'Below baseline';
        } else {
            $key = 'near_baseline';
            $label = 'Near baseline';
        }

        return [
            'key' => $key,
            'label' => $label,
            'delta_percent' => $deltaPercent,
        ];
    }

    protected function buildHeadline(
        Crop $crop,
        float $adjustedYield,
        array $yieldBand,
        array $riskFlags
    ): string {
        $cropName = trim((string) $crop->name) !== '' ? $crop->name : 'This crop';
        $riskCount = count(array_filter($riskFlags));

        if (($yieldBand['key'] ?? null) === 'above_baseline') {
            return "{$cropName} is tracking above its baseline yield potential if current conditions hold.";
        }

        if (($yieldBand['key'] ?? null) === 'below_baseline') {
            return $riskCount > 0
                ? "{$cropName} is currently tracking below baseline because field risks are suppressing yield potential."
                : "{$cropName} is currently tracking below baseline and needs field adjustments to recover yield.";
        }

        if ($riskCount > 0) {
            return "{$cropName} is near baseline, but active field risks could still reduce the final harvest.";
        }

        return "{$cropName} is tracking close to its expected yield under the current field conditions.";
    }
}
