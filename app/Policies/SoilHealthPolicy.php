<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SoilHealth;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class SoilHealthPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'soil_health.view_any');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SoilHealth $soilHealth): bool
    {
        if (RegionScope::roleName($user) === 'farmer') {
            return AuthorityMatrix::can($user, 'soil_health.view')
                && $soilHealth->plot?->farm?->farmer_id === $user->id;
        }

        return AuthorityMatrix::canInRegion($user, 'soil_health.view', $soilHealth->plot?->farm?->region_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return AuthorityMatrix::can($user, 'soil_health.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SoilHealth $soilHealth): bool
    {
        if (RegionScope::roleName($user) === 'farmer') {
            $owns = $soilHealth->plot?->farm?->farmer_id === $user->id;
            return $owns && AuthorityMatrix::can($user, 'soil_health.update');
        }

        return AuthorityMatrix::canInRegion($user, 'soil_health.update', $soilHealth->plot?->farm?->region_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SoilHealth $soilHealth): bool
    {
        if (RegionScope::roleName($user) === 'farmer') {
            $owns = $soilHealth->plot?->farm?->farmer_id === $user->id;
            if (! $owns) {
                return false;
            }

            return AuthorityMatrix::can($user, 'soil_health.delete')
                && strtolower((string) $soilHealth->review_status) !== 'validated';
        }

        return AuthorityMatrix::canInRegion($user, 'soil_health.delete', $soilHealth->plot?->farm?->region_id);
    }

    public function verify(User $user, SoilHealth $soilHealth): bool
    {
        return AuthorityMatrix::canInRegion($user, 'soil_health.verify', $soilHealth->plot?->farm?->region_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SoilHealth $soilHealth): bool
    {
        return AuthorityMatrix::can($user, 'soil_health.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SoilHealth $soilHealth): bool
    {
        return AuthorityMatrix::can($user, 'soil_health.delete');
    }
}
