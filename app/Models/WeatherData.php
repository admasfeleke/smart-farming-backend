<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WeatherData extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'farm_id',
        'plot_id',
        'temperature',
        'humidity',
        'precipitation',
        'wind_speed',
        'soil_moisture',
        'data_source',
        'sensor_device_id',
        'sensor_reading_id',
        'sensor_payload',
        'field_context',
        'battery_level',
        'signal_quality',
        'recorded_at',
    ];

    /**
     * Scope to get weather data for a specific plot
     */
    public function scopeForPlot($query, int $plotId)
    {
        return $query->where('plot_id', $plotId);
    }

    /**
     * Scope to get recent weather data (last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('recorded_at', '>=', now()->subDays(30));
    }

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature' => 'decimal:2',
        'humidity' => 'decimal:2',
        'precipitation' => 'decimal:2',
        'wind_speed' => 'decimal:2',
        'soil_moisture' => 'decimal:2',
        'sensor_payload' => 'array',
        'field_context' => 'array',
        'battery_level' => 'decimal:2',
        'signal_quality' => 'decimal:2',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    /**
     * Scope to get weather data for a specific time range
     */
    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get weather data by data source
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('data_source', $source);
    }
}
