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
                $query->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        $counts = $query->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        // Ensure all severities exist
        $severities = ['low', 'medium', 'high', 'critical'];
        $data = [];
        foreach ($severities as $level) {
            $data[] = $counts[$level] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Alerts',
                    'data' => $data,
                ],
            ],
            'labels' => $severities,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
