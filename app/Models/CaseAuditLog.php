<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAuditLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'from_status',
        'to_status',
        'actor_user_id',
        'actor_role',
        'actor_region_id',
        'note',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

