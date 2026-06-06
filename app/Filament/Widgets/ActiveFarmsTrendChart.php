<?php

namespace App\Filament\Widgets;

use App\Models\Farm;
use App\Models\User;
use App\Support\RegionScope;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ActiveFarmsTrendChart extends ChartWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    protected static ?int $sort = 6;
    public function getHeading(): string
    {
        return 'Active Farms Trend (Last 6 Months)';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $regions = ($user instanceof User && ! RegionScope::isSuperAdmin($user))
            ? RegionScope::accessibleRegionIds($user)
            : null;

        $labels = [];
        $data = [];

        // Single query: count active farms created in the last 6 months, grouped by month
        $start = Carbon::now()->subMonths(5)->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        $query = Farm::query()
            ->where('is_active', 1)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month');

        if (is_array($regions)) {
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('region_id', $regions);
            }
        }

        $counts = $query->pluck('total', 'month');

        for ($i = 5; $i >= 0; $i--) {
            $monthKey = Carbon::now()->subMonths($i)->format('Y-m');
            $labels[] = Carbon::now()->subMonths($i)->format('M');
            $data[]   = (int) ($counts[$monthKey] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active Farms',
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
