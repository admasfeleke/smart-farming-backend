<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountabilityOpsStats;
use App\Filament\Widgets\ActiveFarmsTrendChart;
use App\Filament\Widgets\AdminScopeStats;
use App\Filament\Widgets\AdminSystemStats;
use App\Filament\Widgets\AlertsBySeverityChart;
use App\Filament\Widgets\DiseaseTrendChart;
use App\Filament\Widgets\ExpertResolutionTimeChart;
use App\Filament\Widgets\RoleWorkbenchStats;
use App\Filament\Widgets\TopAffectedCropsChart;
use App\Filament\Widgets\TopAffectedRegionsChart;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return 'Dashboard';
        }

        return match (RegionScope::roleName($user)) {
            'super_admin' => 'Super Admin Workbench',
            'admin' => 'Regional Admin Workbench',
            'supporter' => 'Supporter Workbench',
            'expert' => 'Expert Workbench',
            default => 'Dashboard',
        };
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        return match (RegionScope::roleName($user)) {
            'super_admin' => [
                AccountWidget::class,
                AdminSystemStats::class,
                AccountabilityOpsStats::class,
                DiseaseTrendChart::class,
                AlertsBySeverityChart::class,
                TopAffectedRegionsChart::class,
                TopAffectedCropsChart::class,
                ActiveFarmsTrendChart::class,
                ExpertResolutionTimeChart::class,
            ],
            'admin' => [
                AccountWidget::class,
                AccountabilityOpsStats::class,
                AdminScopeStats::class,
            ],
            'supporter' => [
                AccountWidget::class,
                RoleWorkbenchStats::class,
            ],
            'expert' => [
                AccountWidget::class,
                RoleWorkbenchStats::class,
                ExpertResolutionTimeChart::class,
            ],
            default => [
                AccountWidget::class,
            ],
        };
    }
}
