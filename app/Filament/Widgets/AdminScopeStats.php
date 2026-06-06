<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\DiseaseReport;
use App\Models\Farm;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminScopeStats extends StatsOverviewWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User && RegionScope::roleName($user) === 'admin';
    }

    protected function getHeading(): ?string
    {
        return 'Admin Scope Dashboard';
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $regions = RegionScope::accessibleRegionIds($user);

        $farmQuery = Farm::query()->where('is_active', 1);
        $reportQuery = DiseaseReport::query();
        $alertQuery = Alert::query()->where('status', 'open');

        if (! RegionScope::isSuperAdmin($user)) {
            if ($regions === []) {
                $farmQuery->whereRaw('1 = 0');
                $reportQuery->whereRaw('1 = 0');
                $alertQuery->whereRaw('1 = 0');
            } else {
                $farmQuery->whereIn('region_id', $regions);
                $reportQuery->whereHas('plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
                $alertQuery->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        return [
            Stat::make('Active Farms (Scope)', (clone $farmQuery)->count())
                ->description('Farms in your assigned scope')
                ->color('success')
                ->extraAttributes([
                    'class' => 'kpi-active-farms-scope',
                ]),
            Stat::make('Reviewing Reports', (clone $reportQuery)->whereIn('status', ['new', 'reviewing', 'processing'])->count())
                ->description('Pending verification')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'kpi-reviewing-reports',
                ]),
            Stat::make('Open High/Critical Alerts', (clone $alertQuery)->whereIn('severity', ['high', 'critical'])->count())
                ->description('Immediate response needed')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'kpi-open-high-alerts',
                ]),
            Stat::make('Admin Level', strtoupper((string) ($user->admin_level ?? 'unspecified')))
                ->description('Current operational level')
                ->color('info')
                ->extraAttributes([
                    'class' => 'kpi-admin-level',
                ]),
        ];
    }
}
