<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RegionScope;
use Illuminate\Auth\Access\Response;

class YieldPredictionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'supporter', 'field_officer'], true);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'supporter', 'field_officer'], true);
    }
}