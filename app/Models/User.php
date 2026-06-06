<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'role_id',
        'region_id',
        'admin_level',
        'name',
        'phone',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class, 'farmer_id');
    }

    public function scopedRegions()
    {
        return $this->belongsToMany(Region::class, 'user_region_scopes', 'user_id', 'region_id')
            ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $roleName = strtolower((string) optional($this->role)->name);

        return in_array($roleName, ['super_admin', 'admin', 'supporter', 'expert'], true);
    }
}
