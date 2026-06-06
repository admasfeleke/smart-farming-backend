<?php

namespace App\Filament\Widgets;

use App\Models\DiseaseReport;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopAffectedRegionsChart extends ChartWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    protected static ?int $sort = 5;
    public function getHeading(): string
    {
        return 'Top Affected Regions';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $regions = ($user instanceof User && ! RegionScope::isSuperAdmin($user))
            ? RegionScope::accessibleRegionIds($user)
            : null;

        // Correct join and column references
        $query = DiseaseReport::query()
            ->select('regions.name', DB::raw('COUNT(*) as total'))
            ->join('plots', 'disease_reports.plot_id', '=', 'plots.id')
            ->join('farms', 'plots.farm_id', '=', 'farms.id')
            ->join('regions', 'farms.region_id', '=', 'regions.id');

        if (is_array($regions)) {
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('farms.region_id', $regions);
            }
        }

        $topRegions = $query
            ->groupBy('regions.id', 'regions.name')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'regions.name');

        $labels = $topRegions->keys()->toArray();
        $data = $topRegions->values()->toArray();

        while (count($labels) < 5) {
            $labels[] = '—';
            $data[] = 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Reported Cases',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
