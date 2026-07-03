<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Crop;
use App\Models\Plot;
use App\Models\Planting;
use App\Models\User;
use App\Models\SoilHealth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiseasePreventionService
{
    public function __construct(
        protected WeatherContextService $weatherContextService,
    ) {
    }

    public function analyzeAndGeneratePreventiveAlerts(
        ?int $farmId = null,
        ?int $plotId = null,
        ?int $cropId = null,
        array $conditions = [],
    ): array {
        $plotsQuery = Plot::whereHas('farm', function ($query) {
            $query->where('is_active', true);
        })->with(['farm.region', 'plantings.crop']);

        if ($farmId !== null) {
            $plotsQuery->where('farm_id', $farmId);
        }

        if ($plotId !== null) {
            $plotsQuery->whereKey($plotId);
        }

        $plots = $plotsQuery->get();
        $summary = [
            'plots_considered' => 0,
            'plantings_considered' => 0,
            'alerts_created' => 0,
            'used_supplied_conditions' => $conditions !== [],
        ];

        foreach ($plots as $plot) {
            $this->analyzePlotConditions($plot, $cropId, $conditions, $summary);
        }

        return $summary;
    }

    protected function analyzePlotConditions(
        Plot $plot,
        ?int $cropId = null,
        array $overrideConditions = [],
        array &$summary = [],
    ): void {
        $weatherContext = $this->weatherContextService->buildForPlot($plot, $overrideConditions);

        $summary['plots_considered'] = ($summary['plots_considered'] ?? 0) + 1;

        $plantingsQuery = $plot->plantings()
            ->where('is_active', true)
            ->where('status', 'active');

        if ($cropId !== null) {
            $plantingsQuery->where('crop_id', $cropId);
        }

        $plantings = $plantingsQuery->get();
        if ($plantings->isEmpty()) {
            return;
        }

        foreach ($plantings as $planting) {
            $summary['plantings_considered'] = ($summary['plantings_considered'] ?? 0) + 1;
            $created = $this->analyzePlantingRisk($planting, $weatherContext);
            if ($created) {
                $summary['alerts_created'] = ($summary['alerts_created'] ?? 0) + 1;
            }
        }
    }

    protected function analyzePlantingRisk(Planting $planting, array $weatherContext): bool
    {
        $analysis = $this->buildPreventionAnalysis(
            $planting->crop_id,
            $this->collectObservedConditions($planting->plot, $weatherContext)
        );

        return $this->generateRiskAlerts($planting, $analysis);
    }

    protected function collectObservedConditions(Plot $plot, array $weatherContext): array
    {
        $latestSoil = SoilHealth::forPlot($plot->id)->latest('test_date')->first();
        $observed = $weatherContext['observed'] ?? [];
        $forecast = $weatherContext['forecast'] ?? [];
        $combined = $weatherContext['combined'] ?? [];

        return [
            'temperature' => $this->firstAvailable([
                $combined['temperature'] ?? null,
                $observed['temperature'] ?? null,
                $forecast['temperature'] ?? null,
            ]),
            'humidity' => $this->firstAvailable([
                $combined['humidity'] ?? null,
                $observed['humidity'] ?? null,
                $forecast['humidity'] ?? null,
            ]),
            'precipitation' => $this->firstAvailable([
                $combined['precipitation'] ?? null,
                $observed['precipitation'] ?? null,
                $forecast['precipitation'] ?? null,
            ]),
            'soil_moisture' => $this->firstAvailable([
                $combined['soil_moisture'] ?? null,
                $observed['soil_moisture'] ?? null,
                $forecast['soil_moisture'] ?? null,
                $latestSoil?->moisture_level,
            ]),
            'soil_ph' => $latestSoil?->ph_level,
            'soil_type' => $latestSoil?->soil_type ?: $plot->soil_type,
            'soil_nitrogen' => $latestSoil?->nitrogen,
            'soil_phosphorus' => $latestSoil?->phosphorus,
            'soil_potassium' => $latestSoil?->potassium,
            'organic_matter' => $latestSoil?->organic_matter,
            'weather_samples' => $weatherContext['total_records'] ?? 0,
            'weather_source' => $observed['source'] ?? null,
            'forecast_available' => (bool) data_get($weatherContext, 'forecast.available', false),
        ];
    }

    protected function generateRiskAlerts(Planting $planting, array $analysis): bool
    {
        $plot = $planting->plot;
        $farm = $plot->farm;

        if (($analysis['risk_score'] ?? 0) < 0.6) {
            return false;
        }

        $alertType = $this->determineAlertType($analysis);

        $recentAlert = Alert::where('farm_id', $farm->id)
            ->where('plot_id', $plot->id)
            ->where('planting_id', $planting->id)
            ->where('alert_type', $alertType)
            ->where('is_preventive', true)
            ->whereIn('status', ['open', 'acknowledged'])
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recentAlert) {
            return false;
        }

        $severity = $this->determineSeverity((float) $analysis['risk_score']);
        $this->createPreventiveAlert($planting, $alertType, $severity, $analysis);
        return true;
    }

    protected function determineAlertType(array $analysis): string
    {
        $drivers = collect($analysis['risk_drivers'] ?? [])->map(
            fn ($item) => strtolower((string) ($item['key'] ?? ''))
        );

        if ($drivers->contains('fungal_pressure')) {
            return 'fungal_prevention';
        }
        if ($drivers->contains('bacterial_pressure')) {
            return 'bacterial_prevention';
        }
        if ($drivers->contains('root_zone_pressure')) {
            return 'root_rot_prevention';
        }
        if ($drivers->contains('vector_pressure')) {
            return 'viral_prevention';
        }

        return 'general_prevention';
    }

    protected function determineSeverity(float $riskLevel): string
    {
        if ($riskLevel >= 0.8) {
            return 'high';
        }
        if ($riskLevel >= 0.6) {
            return 'medium';
        }

        return 'low';
    }

    protected function createPreventiveAlert(
        Planting $planting,
        string $alertType,
        string $severity,
        array $analysis
    ): void {
        $plot = $planting->plot;
        $farm = $plot->farm;
        $crop = $planting->crop;

        $alert = Alert::create([
            'farm_id' => $farm->id,
            'plot_id' => $plot->id,
            'planting_id' => $planting->id,
            'alert_type' => $alertType,
            'severity' => $severity,
            'title' => $this->generateAlertTitle($crop->name, $analysis),
            'message' => $this->generateAlertMessage($crop->name, $plot->plot_name, $analysis),
            'status' => 'open',
            'triggered_at' => now(),
            'is_preventive' => true,
            'risk_level' => $analysis['risk_score'],
        ]);

        $this->sendPreventiveNotification($alert, $analysis);
    }

    protected function generateAlertTitle(string $cropName, array $analysis): string
    {
        $riskLabel = ucfirst(str_replace('_', ' ', (string) ($analysis['risk_level'] ?? 'moderate')));
        return "{$riskLabel} disease prevention alert for {$cropName}";
    }

    protected function generateAlertMessage(string $cropName, string $plotName, array $analysis): string
    {
        $headline = $analysis['headline'] ?? "Conditions are changing for {$cropName}.";
        $primaryAction = $analysis['recommendations'][0] ?? 'Review the field and adjust management today.';

        return "{$headline} Plot {$plotName}. {$primaryAction}";
    }

    protected function sendPreventiveNotification(Alert $alert, array $analysis): void
    {
        $users = $this->getAlertRecipients($alert);

        foreach ($users as $user) {
            Log::info("Preventive alert sent to user {$user->name}: {$alert->title}");

            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'preventive_alert',
                'data' => [
                    'alert_id' => $alert->id,
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'severity' => $alert->severity,
                    'risk_level' => $analysis['risk_level'] ?? 'moderate',
                    'risk_score' => $analysis['risk_score'] ?? 0,
                    'recommendations' => $analysis['recommendations'] ?? [],
                ],
                'read_at' => null,
            ]);
        }
    }

    protected function getAlertRecipients(Alert $alert): Collection
    {
        $farm = $alert->farm;
        $users = collect([$farm->farmer]);

        $regionUsers = User::where('region_id', $farm->region_id)
            ->whereHas('role', function ($query) {
                $query->whereIn('name', ['expert', 'supporter', 'admin']);
            })
            ->get();

        return $users->merge($regionUsers);
    }

    public function getPreventiveRecommendationsForCrop(int $cropId, array $conditions): array
    {
        $cacheKey = "preventive_recommendations_{$cropId}_" . md5(serialize($conditions));

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $analysis = $this->buildPreventionAnalysis($cropId, $conditions);

        Cache::put($cacheKey, $analysis, now()->addHours(6));

        return $analysis;
    }

    protected function buildPreventionAnalysis(int $cropId, array $conditions): array
    {
        $crop = Crop::find($cropId);
        $cropName = $crop?->name ?: 'crop';

        $temperature = $this->toFloat($conditions['temperature'] ?? null);
        $humidity = $this->toFloat($conditions['humidity'] ?? null);
        $precipitation = $this->toFloat($conditions['precipitation'] ?? null);
        $soilMoisture = $this->toFloat($conditions['soil_moisture'] ?? null);
        $soilPh = $this->toFloat($conditions['soil_ph'] ?? null);
        $nitrogen = $this->toFloat($conditions['soil_nitrogen'] ?? null);
        $phosphorus = $this->toFloat($conditions['soil_phosphorus'] ?? null);
        $potassium = $this->toFloat($conditions['soil_potassium'] ?? null);
        $organicMatter = $this->toFloat($conditions['organic_matter'] ?? null);
        $soilType = trim((string) ($conditions['soil_type'] ?? ''));

        $riskDrivers = [];
        $watchItems = [];
        $actions = [];
        $riskScore = 0.18;

        if (($humidity ?? 0) >= 82 && ($temperature ?? 0) >= 18 && ($temperature ?? 0) <= 30) {
            $riskScore += 0.28;
            $riskDrivers[] = ['key' => 'fungal_pressure', 'label' => 'High humidity is favoring fungal spread.'];
            $watchItems[] = 'Watch leaf undersides and lower canopy for fresh spotting or mildew growth.';
            $actions[] = 'Open the canopy where possible and avoid wetting foliage late in the day.';
        } elseif (($humidity ?? 0) >= 72) {
            $riskScore += 0.14;
            $riskDrivers[] = ['key' => 'surface_wetness', 'label' => 'Leaf wetness conditions are building up.'];
        }

        if (($precipitation ?? 0) >= 35 || ($soilMoisture ?? 0) >= 75) {
            $riskScore += 0.24;
            $riskDrivers[] = ['key' => 'root_zone_pressure', 'label' => 'Wet root-zone conditions are increasing soil-borne disease pressure.'];
            $watchItems[] = 'Inspect low spots and poorly drained areas first.';
            $actions[] = 'Improve drainage flow and reduce unnecessary irrigation until the root zone dries back.';
        }

        if (($temperature ?? 0) >= 28 && ($humidity ?? 0) >= 70) {
            $riskScore += 0.16;
            $riskDrivers[] = ['key' => 'bacterial_pressure', 'label' => 'Warm humid conditions can accelerate bacterial infection.'];
            $actions[] = 'Limit field work when foliage is wet to reduce spread between plants.';
        }

        if (($temperature ?? 0) >= 30 && ($soilMoisture ?? 100) <= 30) {
            $riskScore += 0.12;
            $riskDrivers[] = ['key' => 'stress_pressure', 'label' => 'Heat and dryness are weakening crop resilience.'];
            $watchItems[] = 'Look for wilting, leaf scorch, and uneven growth that can open the door to secondary disease.';
            $actions[] = 'Stabilize moisture with timely irrigation and retain soil cover where available.';
        }

        if (($soilPh ?? 6.5) < 5.5 || ($soilPh ?? 6.5) > 7.8) {
            $riskScore += 0.08;
            $watchItems[] = 'Suboptimal soil pH may reduce nutrient uptake and weaken disease resistance.';
            $actions[] = 'Review soil amendment options to move pH toward the productive range for this crop.';
        }

        if (
            (($nitrogen ?? 999) < 35) ||
            (($phosphorus ?? 999) < 18) ||
            (($potassium ?? 999) < 90) ||
            (($organicMatter ?? 999) < 2.0)
        ) {
            $riskScore += 0.1;
            $riskDrivers[] = ['key' => 'nutrition_pressure', 'label' => 'Soil nutrition is leaving the crop less resilient to infection.'];
            $actions[] = 'Correct the weakest nutrient first instead of applying broad inputs without a target.';
        }

        if ($soilType !== '' && in_array(strtolower($soilType), ['clay', 'heavy clay'], true)) {
            $watchItems[] = 'Heavy soil can hold water longer, so keep checking for prolonged wetness after rain.';
        }

        $riskScore = min($riskScore, 0.95);
        $riskLevel = $riskScore >= 0.75
            ? 'high'
            : ($riskScore >= 0.5 ? 'moderate' : 'low');

        if ($actions === []) {
            $actions[] = 'Maintain field scouting, sanitation, and stable irrigation because current disease pressure is limited.';
        }

        if ($watchItems === []) {
            $watchItems[] = 'Continue checking new growth and shaded canopy zones during routine field visits.';
        }

        $actions = array_values(array_unique($actions));
        $watchItems = array_values(array_unique($watchItems));

        return [
            'crop_name' => $cropName,
            'risk_level' => $riskLevel,
            'risk_score' => round($riskScore, 2),
            'headline' => $this->buildHeadline($cropName, $riskLevel, $riskDrivers),
            'risk_drivers' => $riskDrivers,
            'watch_items' => $watchItems,
            'recommendations' => $actions,
            'conditions_used' => array_filter([
                'temperature' => $temperature,
                'humidity' => $humidity,
                'precipitation' => $precipitation,
                'soil_moisture' => $soilMoisture,
                'soil_ph' => $soilPh,
                'soil_type' => $soilType !== '' ? $soilType : null,
            ], static fn ($value) => $value !== null && $value !== ''),
        ];
    }

    protected function firstAvailable(array $values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function buildHeadline(string $cropName, string $riskLevel, array $riskDrivers): string
    {
        if ($riskDrivers === []) {
            return "Current conditions do not show strong disease pressure for {$cropName}.";
        }

        $primary = $riskDrivers[0]['label'] ?? 'Disease pressure is building.';
        return ucfirst($riskLevel) . " disease risk for {$cropName}. {$primary}";
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
