<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesticideProduct extends Model
{
    protected $fillable = [
        'product_name',
        'localized_names',
        'active_ingredient',
        'localized_active_ingredients',
        'formulation',
        'product_type',
        'registration_status',
        'label_warning',
        'localized_label_warnings',
        'is_active',
    ];

    protected $casts = [
        'localized_names' => 'array',
        'localized_active_ingredients' => 'array',
        'localized_label_warnings' => 'array',
        'is_active' => 'boolean',
    ];

    public function treatmentRecommendations()
    {
        return $this->hasMany(TreatmentRecommendation::class);
    }
}
