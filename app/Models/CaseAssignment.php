<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAssignment extends Model
{
    protected $fillable = [
        'disease_report_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'priority',
        'due_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'due_at'        => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function diseaseReport()
    {
        return $this->belongsTo(DiseaseReport::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}

