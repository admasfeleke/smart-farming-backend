<?php

namespace App\Filament\Widgets;

use App\Models\Role;
use App\Models\User;
use App\Models\DiseaseReport;
use App\Models\Alert;
use App\Models\Farm;
use App\Support\RegionScope;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminSystemStats extends StatsOverviewWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $regions = RegionScope::isSuperAdmin($user) ? null : RegionScope::accessibleRegionIds($user);

        $farmerQuery = User::query()->where('role_id', Role::farmer()->id);
        $farmQuery = Farm::query()->where('is_active', 1);
        $reportQuery = DiseaseReport::query();
        $alertQuery = Alert::query()
            ->whereIn('severity', ['high', 'critical'])
            ->where('status', 'open');

        if (is_array($regions)) {
            if ($regions === []) {
                $farmerQuery->whereRaw('1 = 0');
                $farmQuery->whereRaw('1 = 0');
                $reportQuery->whereRaw('1 = 0');
                $alertQuery->whereRaw('1 = 0');
            } else {
                $farmerQuery->whereIn('region_id', $regions);
                $farmQuery->whereIn('region_id', $regions);
                $reportQuery->whereHas('plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
                $alertQuery->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        return [
            Stat::make(
                'Registered Farmers',
                (clone $farmerQuery)->count()
            )
                ->description('Total number of farmers registered in the system') // improved description
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(route('filament.admin.pages.farmers-overview'))
                ->extraAttributes([
                    'class' => 'text-center kpi-farmers', // center the number
                ])
            ,

            Stat::make(
                'Active Farms',
                (clone $farmQuery)->count() // only active farms
            )
                ->description('Number of farms currently active in the system') // clear, professional
                ->icon('heroicon-o-map') // map icon represents farms
                ->color('success') // green for active/healthy
                ->url(route('filament.admin.pages.farms-overview')) // clickable card
                ->extraAttributes([
                    'class' => 'text-center kpi-active-farms', // center the number
                ]),

            Stat::make('Disease Reports', (clone $reportQuery)->count())
                ->description('Total AI and human-reported plant disease observations')
                ->icon('heroicon-o-bug-ant')
                ->color('red')
                ->url(route('filament.admin.pages.disease-reports-overview'))
                ->extraAttributes([
                    'class' => 'kpi-disease-reports',
                ]),

            Stat::make(
                'High Severity Alerts',
                (clone $alertQuery)->count()
            )
                ->description('Critical crop health risks requiring attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(route('filament.admin.pages.alerts-overview'))
                ->extraAttributes([
                    'class' => 'kpi-high-alerts',
                ]),

        ];
    }
}
