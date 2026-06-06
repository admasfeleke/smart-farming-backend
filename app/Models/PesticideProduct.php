<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesticideProduct extends Model
{
    protected $fillable = [
        'product_name',
        'active_ingredient',
        'formulation',
        'product_type',
        'registration_status',
        'label_warning',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function treatmentRecommendations()
    {
        return $this->hasMany(TreatmentRecommendation::class);
    }
}
