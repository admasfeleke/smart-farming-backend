<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    protected $fillable = [
        'farm_id',
        'plot_name',
        'area_hectares',
        'soil_type',
        'is_active',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function plantings()
    {
        return $this->hasMany(Planting::class);
    }
}
