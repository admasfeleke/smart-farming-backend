<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAssignment extends Model
{
    protected $fillable = [
        'case_type',
        'disease_report_id',
        'soil_health_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'priority',
        'due_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'due_at'        => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function diseaseReport()
    {
        return $this->belongsTo(DiseaseReport::class);
    }

    public function soilHealth()
    {
        return $this->belongsTo(SoilHealth::class);
    }

    public function caseRecord()
    {
        return $this->case_type === 'soil_health'
            ? $this->soilHealth
            : $this->diseaseReport;
    }

    public function caseRegionId(): ?int
    {
        return $this->case_type === 'soil_health'
            ? $this->soilHealth?->plot?->farm?->region_id
            : $this->diseaseReport?->plot?->farm?->region_id;
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
