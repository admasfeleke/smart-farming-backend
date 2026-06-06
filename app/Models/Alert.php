<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'disease_report_id',
        'farm_id',
        'plot_id',
        'planting_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'status',
        'triggered_at',
        'is_preventive',
        'risk_level',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'owner_user_id',
        'last_action_by',
        'last_action_at',
        'resolution_reason_code',
        'resolution_comment',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_action_at' => 'datetime',
        'is_preventive' => 'boolean',
        'risk_level' => 'float',
    ];

    public function diseaseReport()
    {
        return $this->belongsTo(DiseaseReport::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function planting(): BelongsTo
    {
        return $this->belongsTo(Planting::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function lastActionBy()
    {
        return $this->belongsTo(User::class, 'last_action_by');
    }
}
