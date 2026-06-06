<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\CaseAssignment;
use App\Models\DiseaseReport;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountabilityOpsStats extends StatsOverviewWidget
{
    protected static bool $isLazy = true;
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['super_admin', 'admin'], true);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $reportQuery = DiseaseReport::query();
        $alertQuery = Alert::query();
        $assignmentQuery = CaseAssignment::query()->where('status', 'active');

        if (! RegionScope::isSuperAdmin($user)) {
            $regions = RegionScope::accessibleRegionIds($user);
            if ($regions === []) {
                $reportQuery->whereRaw('1 = 0');
                $alertQuery->whereRaw('1 = 0');
                $assignmentQuery->whereRaw('1 = 0');
            } else {
                $reportQuery->whereHas('plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
                $alertQuery->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
                $assignmentQuery->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        return [
            Stat::make(
                'Pending Verification',
                (clone $reportQuery)->whereIn('status', ['new', 'reviewing', 'processing'])->count()
            )->description('Reports awaiting decision')->color('warning')
                ->extraAttributes([
                    'class' => 'kpi-pending-verification',
                ]),

            Stat::make(
                'Critical Open Alerts',
                (clone $alertQuery)->where('status', 'open')->whereIn('severity', ['high', 'critical'])->count()
            )->description('Requires immediate accountability')->color('danger')
                ->extraAttributes([
                    'class' => 'kpi-critical-open-alerts',
                ]),

            Stat::make(
                'Overdue Assignments',
                (clone $assignmentQuery)->whereNotNull('due_at')->where('due_at', '<', now())->count()
            )->description('Assigned cases past SLA')->color('danger')
                ->extraAttributes([
                    'class' => 'kpi-overdue-assignments',
                ]),
        ];
    }
}
