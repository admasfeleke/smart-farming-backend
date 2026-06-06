<?php

namespace App\Providers;

use App\Models\Alert;
use App\Models\CaseAssignment;
use App\Models\CaseAuditLog;
use App\Models\DiseaseReport;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\Plot;
use App\Policies\AlertPolicy;
use App\Policies\CaseAssignmentPolicy;
use App\Policies\CaseAuditLogPolicy;
use App\Policies\DiseaseReportPolicy;
use App\Policies\FarmPolicy;
use App\Policies\PlantingPolicy;
use App\Policies\PlotPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Farm::class => FarmPolicy::class,
        Plot::class => PlotPolicy::class,
        Planting::class => PlantingPolicy::class,
        DiseaseReport::class => DiseaseReportPolicy::class,
        Alert::class => AlertPolicy::class,
        CaseAssignment::class => CaseAssignmentPolicy::class,
        CaseAuditLog::class => CaseAuditLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
