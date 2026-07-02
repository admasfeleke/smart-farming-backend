<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AlertsBySeverityChart extends ChartWidget
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
        return 'Alerts by Severity';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $regions = ($user instanceof User && ! RegionScope::isSuperAdmin($user))
            ? RegionScope::accessibleRegionIds($user)
            : null;

        // Aggregate alerts counts by severity
        $query = Alert::query();
        if (is_array($regions)) {
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($regions) {
                    // Include alerts with disease reports
                    $q->whereHas('diseaseReport.plot.farm', fn ($subq) => $subq->whereIn('region_id', $regions))
                      // Also include alerts with direct farm_id
                      ->orWhereHas('farm', fn ($subq) => $subq->whereIn('region_id', $regions));
                });
            }
        }

        $counts = $query->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        // Ensure all severities exist
        $severities = ['low', 'medium', 'high', 'critical'];
        $data = [];
        $colors = [
            'low' => 'rgba(34, 197, 94, 0.7)',      // Green
            'medium' => 'rgba(251, 146, 60, 0.7)',  // Orange
            'high' => 'rgba(239, 68, 68, 0.7)',     // Red
            'critical' => 'rgba(127, 29, 29, 0.7)', // Dark red
        ];
        $backgroundColor = [];

        foreach ($severities as $level) {
            $data[] = $counts[$level] ?? 0;
            $backgroundColor[] = $colors[$level];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Alerts',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderColor' => array_map(fn ($c) => str_replace('0.7', '1', $c), $backgroundColor),
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $severities,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
