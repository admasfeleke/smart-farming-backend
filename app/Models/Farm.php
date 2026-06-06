<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Farm extends Model
{
    protected $fillable = [
        'farmer_id',
        'region_id',
        'farm_name',
        'latitude',
        'longitude',
        'area_hectares',
        'farm_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'area_hectares' => 'decimal:2',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function plots()
    {
        return $this->hasMany(Plot::class);
    }
}
