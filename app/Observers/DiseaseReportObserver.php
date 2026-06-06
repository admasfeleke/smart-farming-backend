<?php

namespace App\Observers;

use App\Models\DiseaseReport;
use App\Services\AlertService;

class DiseaseReportObserver
{
    public function created(DiseaseReport $diseaseReport): void
    {
        app(AlertService::class)->handleDiseaseReport($diseaseReport);
    }

    public function updated(DiseaseReport $diseaseReport): void
    {
        // Re-evaluate alert on verification/status transitions and severity changes.
        if ($diseaseReport->wasChanged(['status', 'severity'])) {
            app(AlertService::class)->handleDiseaseReport($diseaseReport);
        }
    }
}
