<?php

namespace App\Filament\Widgets;

use App\Models\CaseAssignment;
use App\Models\User;
use App\Support\RegionScope;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpertResolutionTimeChart extends ChartWidget
{
    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['super_admin', 'expert'], true);
    }

    public function getHeading(): string
    {
        return 'Expert Case Resolution Time (Last 6 Months)';
    }

    public function getDescription(): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return RegionScope::isSuperAdmin($user)
            ? 'Average hours from case assignment to completion by experts, with SLA breach rate'
            : 'Average hours from assignment to your completed cases, with SLA breach rate';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->emptyData();
        }

        $isSuperAdmin = RegionScope::isSuperAdmin($user);
        $start = Carbon::now()->subMonths(5)->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        // Base query: completed case assignments closed within the 6-month window.
        // Resolution time = time from assignment creation to completion (completed_at).
        $base = CaseAssignment::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end]);

        // For experts: scope to their own assignments only.
        if (! $isSuperAdmin) {
            $base->where('assigned_to_user_id', $user->id);
        } else {
            // For super_admin: scope to expert-role users only so we measure
            // expert performance specifically, not all backoffice users.
            $base->whereHas('assignedTo.role', fn ($q) => $q->where('name', 'expert'));
        }

        // Apply region scope for non-super-admins (experts see their region's cases).
        if (! $isSuperAdmin) {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                return $this->emptyData();
            }
            $base->whereHas(
                'diseaseReport.plot.farm',
                fn ($q) => $q->whereIn('region_id', $regionIds)
            );
        }

        // Query 1: average resolution hours per month.
        $avgHours = (clone $base)
            ->select(
                DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as month"),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('avg_hours', 'month');

        // Query 2: SLA breach rate per month — % of completed cases that finished
        // after their due_at deadline. Only cases that had a due_at set are counted.
        $slaStats = (clone $base)
            ->whereNotNull('due_at')
            ->select(
                DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN completed_at > due_at THEN 1 ELSE 0 END) as breached')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $labels       = [];
        $avgHoursData = [];
        $breachData   = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthKey = Carbon::now()->subMonths($i)->format('Y-m');
            $labels[] = Carbon::now()->subMonths($i)->format('M');

            // Use ->get() instead of direct array access — Collection throws on
            // missing keys, whereas ->get() safely returns null.
            $hours = $avgHours->get($monthKey);
            $avgHoursData[] = $hours !== null ? round((float) $hours, 1) : 0;

            $sla = $slaStats->get($monthKey);
            $breachData[] = ($sla && $sla->total > 0)
                ? round(($sla->breached / $sla->total) * 100, 1)
                : 0;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Avg Resolution Time (hours)',
                    'data'            => $avgHoursData,
                    'borderColor'     => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'yAxisID'         => 'y',
                    'tension'         => 0.3,
                    'fill'            => true,
                ],
                [
                    'label'           => 'SLA Breach Rate (%)',
                    'data'            => $breachData,
                    'borderColor'     => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'yAxisID'         => 'y1',
                    'tension'         => 0.3,
                    'borderDash'      => [5, 5],
                    'fill'            => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type'     => 'linear',
                    'display'  => true,
                    'position' => 'left',
                    'title'    => [
                        'display' => true,
                        'text'    => 'Hours',
                    ],
                    'beginAtZero' => true,
                ],
                'y1' => [
                    'type'     => 'linear',
                    'display'  => true,
                    'position' => 'right',
                    'title'    => [
                        'display' => true,
                        'text'    => 'Breach %',
                    ],
                    'beginAtZero' => true,
                    'max'         => 100,
                    'grid'        => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'mode' => 'index',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function emptyData(): array
    {
        $labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $labels[] = Carbon::now()->subMonths($i)->format('M');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Avg Resolution Time (hours)',
                    'data'  => array_fill(0, 6, 0),
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'SLA Breach Rate (%)',
                    'data'  => array_fill(0, 6, 0),
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
