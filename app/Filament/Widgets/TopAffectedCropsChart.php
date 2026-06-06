<?php

namespace App\Filament\Widgets;

use App\Models\DiseaseReport;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopAffectedCropsChart extends ChartWidget
{
    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    protected static ?int $sort = 4;
    public function getHeading(): string
    {
        return 'Top Affected Crops';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $regions = ($user instanceof User && ! RegionScope::isSuperAdmin($user))
            ? RegionScope::accessibleRegionIds($user)
            : null;

        $query = DiseaseReport::query();
        if (is_array($regions)) {
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        $topCrops = $query->select('crop_id', DB::raw('COUNT(*) as total'))
            ->with('crop')
            ->groupBy('crop_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $labels = $topCrops->pluck('crop.name')->toArray();
        $data = $topCrops->pluck('total')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disease Reports',
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
