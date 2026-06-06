<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedInference extends Model
{
    protected $fillable = [
        'disease_report_id',
        'crop_id',
        'image_path',
        'gate_code',
        'gate_stage',
        'selected_crop',
        'detected_crop',
        'confidence_score',
        'message',
        'model_version',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function diseaseReport()
    {
        return $this->belongsTo(DiseaseReport::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }
}

