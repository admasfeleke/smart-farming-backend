<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use App\Models\Region;
use App\Support\RegionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanMetadataTrendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (RegionScope::roleName($user) === 'farmer') {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $days = max(1, min((int) $request->query('days', 30), 365));
        $since = now()->subDays($days);

        $query = DiseaseReport::query()
            ->whereNotNull('scan_metadata')
            ->where('reported_at', '>=', $since)
            ->with([
                'crop:id,name',
                'plot.farm:id,region_id',
            ]);

        $regionIds = RegionScope::accessibleRegionIds($user);
        if ($regionIds === []) {
            return response()->json([
                'data' => [
                    'window_days' => $days,
                    'generated_at' => now()->toISOString(),
                    'totals' => [
                        'reports_with_metadata' => 0,
                    ],
                    'by_crop' => [],
                    'by_region' => [],
                ],
            ]);
        }

        $query->whereHas('plot.farm', fn ($farmQuery) => $farmQuery->whereIn('region_id', $regionIds));

        $reports = $query->get();
        $regionNameById = Region::query()
            ->whereIn('id', $regionIds)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();

        $byCrop = [];
        $byRegion = [];

        foreach ($reports as $report) {
            $metadata = is_array($report->scan_metadata) ? $report->scan_metadata : [];
            if ($metadata === []) {
                continue;
            }

            $cropId = (int) $report->crop_id;
            $cropName = (string) optional($report->crop)->name;
            $regionId = (int) optional($report->plot?->farm)->region_id;
            if ($regionId === 0) {
                continue;
            }

            $cropKey = (string) $cropId;
            if (! isset($byCrop[$cropKey])) {
                $byCrop[$cropKey] = $this->emptyBucket($cropId, $cropName);
            }
            $byCrop[$cropKey] = $this->accumulateBucket($byCrop[$cropKey], $metadata);

            $regionKey = (string) $regionId;
            if (! isset($byRegion[$regionKey])) {
                $byRegion[$regionKey] = $this->emptyBucket(
                    $regionId,
                    (string) ($regionNameById[$regionId] ?? 'Region '.$regionId)
                );
            }
            $byRegion[$regionKey] = $this->accumulateBucket($byRegion[$regionKey], $metadata);
        }

        return response()->json([
            'data' => [
                'window_days' => $days,
                'generated_at' => now()->toISOString(),
                'totals' => [
                    'reports_with_metadata' => array_sum(array_map(
                        fn (array $bucket): int => (int) $bucket['reports'],
                        $byCrop
                    )),
                ],
                'by_crop' => $this->finalizeBuckets(array_values($byCrop)),
                'by_region' => $this->finalizeBuckets(array_values($byRegion)),
            ],
        ]);
    }

    /**
     * @return array{
     *   id:int,
     *   name:string,
     *   reports:int,
     *   symptom_days_sum:float,
     *   symptom_days_count:int,
     *   recent_rain_true:int,
     *   recent_rain_known:int,
     *   growth_stage_breakdown:array<string,int>
     * }
     */
    private function emptyBucket(int $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'reports' => 0,
            'symptom_days_sum' => 0.0,
            'symptom_days_count' => 0,
            'recent_rain_true' => 0,
            'recent_rain_known' => 0,
            'growth_stage_breakdown' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function accumulateBucket(array $bucket, array $metadata): array
    {
        $bucket['reports'] = ((int) $bucket['reports']) + 1;

        if (isset($metadata['symptom_days']) && is_numeric($metadata['symptom_days'])) {
            $bucket['symptom_days_sum'] = ((float) $bucket['symptom_days_sum']) + (float) $metadata['symptom_days'];
            $bucket['symptom_days_count'] = ((int) $bucket['symptom_days_count']) + 1;
        }

        if (array_key_exists('recent_rain', $metadata)) {
            $bucket['recent_rain_known'] = ((int) $bucket['recent_rain_known']) + 1;

            if (filter_var($metadata['recent_rain'], FILTER_VALIDATE_BOOL)) {
                $bucket['recent_rain_true'] = ((int) $bucket['recent_rain_true']) + 1;
            }
        }

        $growthStage = strtolower(trim((string) ($metadata['growth_stage'] ?? '')));
        if ($growthStage !== '') {
            $breakdown = (array) $bucket['growth_stage_breakdown'];
            $breakdown[$growthStage] = ((int) ($breakdown[$growthStage] ?? 0)) + 1;
            $bucket['growth_stage_breakdown'] = $breakdown;
        }

        return $bucket;
    }

    /**
     * @param  array<int, array<string, mixed>>  $buckets
     * @return array<int, array<string, mixed>>
     */
    private function finalizeBuckets(array $buckets): array
    {
        $final = array_map(function (array $bucket): array {
            $symptomDaysCount = (int) $bucket['symptom_days_count'];
            $recentRainKnown = (int) $bucket['recent_rain_known'];

            return [
                'id' => (int) $bucket['id'],
                'name' => (string) $bucket['name'],
                'reports' => (int) $bucket['reports'],
                'avg_symptom_days' => $symptomDaysCount > 0
                    ? round(((float) $bucket['symptom_days_sum']) / $symptomDaysCount, 2)
                    : null,
                'recent_rain_rate' => $recentRainKnown > 0
                    ? round(((int) $bucket['recent_rain_true']) / $recentRainKnown, 4)
                    : null,
                'growth_stage_breakdown' => (array) $bucket['growth_stage_breakdown'],
            ];
        }, $buckets);

        usort($final, fn (array $a, array $b): int => $b['reports'] <=> $a['reports']);

        return $final;
    }
}

