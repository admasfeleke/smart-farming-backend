<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SoilHealth extends Model
{
    use HasFactory;

    protected $table = 'soil_health';

    protected $fillable = [
        'plot_id',
        'ph_level',
        'nitrogen',
        'phosphorus',
        'potassium',
        'organic_matter',
        'soil_type',
        'moisture_level',
        'test_date',
        'recommendations',
        'test_method',
        'data_source',
        'sensor_device_id',
        'sensor_reading_id',
        'sensor_payload',
        'field_context',
        'confidence_score',
        'tested_by',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_reason_code',
        'review_comment',
        'evidence_url',
        'evidence_type',
    ];

    /**
     * Scope to get soil health data for a specific plot
     */
    public function scopeForPlot($query, int $plotId)
    {
        return $query->where('plot_id', $plotId);
    }

    /**
     * Scope to get recent soil health data (last 6 months)
     */
    public function scopeRecent($query)
    {
        return $query->where('test_date', '>=', now()->subMonths(6));
    }

    protected $casts = [
        'test_date' => 'date',
        'ph_level' => 'decimal:2',
        'nitrogen' => 'decimal:2',
        'phosphorus' => 'decimal:2',
        'potassium' => 'decimal:2',
        'organic_matter' => 'decimal:2',
        'moisture_level' => 'decimal:2',
        'sensor_payload' => 'array',
        'field_context' => 'array',
        'confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function testedBy()
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignments()
    {
        return $this->hasMany(CaseAssignment::class);
    }

    /**
     * Scope to get soil health data by test method
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('test_method', $method);
    }

    /**
     * Get soil health status based on pH level
     */
    public function getHealthStatusAttribute()
    {
        if ($this->ph_level === null) {
            return 'unknown';
        }

        $ph = (float) $this->ph_level;
        
        if ($ph >= 6.0 && $ph <= 7.0) {
            return 'optimal';
        } elseif (($ph >= 5.5 && $ph < 6.0) || ($ph > 7.0 && $ph <= 7.5)) {
            return 'good';
        } elseif (($ph >= 5.0 && $ph < 5.5) || ($ph > 7.5 && $ph <= 8.0)) {
            return 'moderate';
        } else {
            return 'poor';
        }
    }
}
