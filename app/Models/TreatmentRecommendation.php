<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TreatmentRecommendation extends Model
{
    protected $fillable = [
        'crop_id',
        'pesticide_product_id',
        'disease_key',
        'disease_keyword',
        'recommendation_type',
        'title',
        'localized_content',
        'summary',
        'natural_treatment',
        'modern_treatment',
        'dosage_text',
        'application_timing',
        'pre_harvest_interval_days',
        're_entry_interval_hours',
        'max_applications',
        'ppe',
        'restrictions',
        'monitoring_steps',
        'prevention_steps',
        'approval_status',
        'approved_by',
        'approved_at',
        'is_active',
    ];

    protected $casts = [
        'localized_content' => 'array',
        'monitoring_steps' => 'array',
        'prevention_steps' => 'array',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
        'pre_harvest_interval_days' => 'integer',
        're_entry_interval_hours' => 'integer',
        'max_applications' => 'integer',
    ];

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function pesticideProduct()
    {
        return $this->belongsTo(PesticideProduct::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('approval_status', 'approved');
    }
}
