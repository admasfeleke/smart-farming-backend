<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Planting extends Model
{
    protected $fillable = [
        'plot_id',
        'crop_id',
        'planting_date',
        'expected_harvest_date',
        'status',
        'is_active',
    ];

    protected $casts = [
        'planting_date' => 'date',
        'expected_harvest_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function diseaseReports()
    {
        return $this->hasMany(DiseaseReport::class);
    }
}
