<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegationAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'action',
        'before_json',
        'after_json',
        'note',
        'created_at',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
