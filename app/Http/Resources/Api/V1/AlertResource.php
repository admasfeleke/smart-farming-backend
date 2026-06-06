<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disease_report_id' => $this->disease_report_id,
            'farm_id' => $this->farm_id,
            'plot_id' => $this->plot_id,
            'planting_id' => $this->planting_id,
            'farm_name' => $this->farm?->farm_name,
            'plot_name' => $this->plot?->plot_name,
            'alert_type' => $this->alert_type,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status,
            'is_preventive' => (bool) $this->is_preventive,
            'risk_level' => $this->risk_level,
            'triggered_at' => optional($this->triggered_at)->toISOString(),
            'acknowledged_at' => optional($this->acknowledged_at)->toISOString(),
            'resolved_at' => optional($this->resolved_at)->toISOString(),
        ];
    }
}