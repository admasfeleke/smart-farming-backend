<?php

namespace App\Filament\Widgets;

use App\Models\DiseaseReport;
use App\Models\User;
use App\Support\RegionScope;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DiseaseTrendChart extends ChartWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    public function getHeading(): string
    {
        return 'Disease Trend (Last 6 Months)';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $regions = ($user instanceof User && ! RegionScope::isSuperAdmin($user))
            ? RegionScope::accessibleRegionIds($user)
            : null;

        // Start from 5 months ago until now
        $start = Carbon::now()->subMonths(5)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        // Aggregate counts per month
        $query = DiseaseReport::query();
        if (is_array($regions)) {
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        $reports = $query->select(
                DB::raw("DATE_FORMAT(reported_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total')
            )
            ->whereBetween('reported_at', [$start, $end])
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $labels = [];
        $data = [];

        // Fill last 6 months with counts, even if 0
        for ($i = 5; $i >= 0; $i--) {
            $monthKey = Carbon::now()->subMonths($i)->format('Y-m');
            $labels[] = Carbon::now()->subMonths($i)->format('M');
            $data[] = $reports[$monthKey] ?? 0;
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
        return 'line';
    }
}
