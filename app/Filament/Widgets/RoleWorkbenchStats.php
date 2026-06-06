<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\CaseAssignment;
use App\Models\DiseaseReport;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RoleWorkbenchStats extends StatsOverviewWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['supporter', 'expert'], true);
    }

    protected function getHeading(): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return RegionScope::roleName($user) === 'expert'
            ? 'Expert Workbench'
            : 'Supporter Workbench';
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $regionIds = RegionScope::accessibleRegionIds($user);

        $assignmentQuery = CaseAssignment::query()
            ->where('assigned_to_user_id', $user->id)
            ->where('status', 'active');

        $reviewQueue = DiseaseReport::query()
            ->whereIn('status', ['new', 'reviewing', 'processing']);

        $criticalAlerts = Alert::query()
            ->where('status', 'open')
            ->whereIn('severity', ['high', 'critical']);

        if ($regionIds !== []) {
            $reviewQueue->whereHas('plot.farm', fn ($q) => $q->whereIn('region_id', $regionIds));
            $criticalAlerts->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regionIds));
            $assignmentQuery->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regionIds));
        } else {
            $reviewQueue->whereRaw('1 = 0');
            $criticalAlerts->whereRaw('1 = 0');
            $assignmentQuery->whereRaw('1 = 0');
        }

        return [
            Stat::make('My Active Assignments', (clone $assignmentQuery)->count())
                ->description('Cases assigned to you')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'kpi-my-active-assignments',
                ]),
            Stat::make('Overdue Assignments', (clone $assignmentQuery)->whereNotNull('due_at')->where('due_at', '<', now())->count())
                ->description('Past due SLA')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'kpi-overdue-assignments',
                ]),
            Stat::make('Open Critical Alerts', (clone $criticalAlerts)->count())
                ->description('High-priority regional alerts')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'kpi-open-critical-alerts',
                ]),
            Stat::make('Review Queue', (clone $reviewQueue)->count())
                ->description('Reports waiting verification')
                ->color('info')
                ->extraAttributes([
                    'class' => 'kpi-review-queue',
                ]),
        ];
    }
}
