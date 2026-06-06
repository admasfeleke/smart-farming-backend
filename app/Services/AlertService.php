<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\DiseaseReport;

class AlertService
{
    public function handleDiseaseReport(DiseaseReport $report): void
    {
        if (strtolower((string) $report->status) !== 'confirmed') {
            return;
        }

        if (! in_array($report->severity, ['high', 'critical'], true)) {
            return;
        }

        $plot = $report->plot;
        $farm = $plot?->farm;

        $existingAlert = Alert::where('disease_report_id', $report->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        $payload = [
            'farm_id' => $farm?->id,
            'plot_id' => $plot?->id,
            'planting_id' => $report->planting_id,
            'alert_type' => 'disease_severity',
            'severity' => $report->severity === 'critical' ? 'critical' : 'high',
            'title' => 'Severe crop disease detected',
            'message' => sprintf(
                '%s detected with %s severity.',
                $report->disease_name,
                $report->severity
            ),
            'triggered_at' => now(),
        ];

        if ($existingAlert) {
            $existingAlert->update($payload);
            return;
        }

        Alert::create([
            'disease_report_id' => $report->id,
            ...$payload,
            'status' => 'open',
        ]);
    }
}