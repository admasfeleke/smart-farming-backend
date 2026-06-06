<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    protected $fillable = [
        'name',
        'scientific_name',
        'crop_type',
        'is_active',
    ];

    public function plantings()
    {
        return $this->hasMany(Planting::class);
    }

    public function diseaseReports()
    {
        return $this->hasMany(DiseaseReport::class);
    }

    public function treatmentRecommendations()
    {
        return $this->hasMany(TreatmentRecommendation::class);
    }
}
