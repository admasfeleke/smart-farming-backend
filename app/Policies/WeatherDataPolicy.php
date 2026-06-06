<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeatherData;
use App\Support\RegionScope;
use Illuminate\Auth\Access\Response;

class WeatherDataPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array(
            RegionScope::roleName($user),
            ['super_admin', 'admin', 'supporter', 'field_officer', 'farmer'],
            true
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WeatherData $weatherData): bool
    {
        $role = RegionScope::roleName($user);
        if (! in_array($role, ['super_admin', 'admin', 'supporter', 'field_officer', 'farmer'], true)) {
            return false;
        }

        // Farmers can only view their own farm data.
        if ($role === 'farmer') {
            return $weatherData->farm?->farmer_id === $user->id;
        }

        $regionIds = RegionScope::accessibleRegionIds($user);
        if ($regionIds === []) {
            return false;
        }

        return in_array($weatherData->region_id, $regionIds, true)
            || in_array($weatherData->farm?->region_id, $regionIds, true);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'supporter', 'field_officer', 'farmer'], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WeatherData $weatherData): bool
    {
        if (! $this->view($user, $weatherData)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WeatherData $weatherData): bool
    {
        if (! $this->view($user, $weatherData)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WeatherData $weatherData): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'supporter', 'field_officer'], true);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WeatherData $weatherData): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'supporter', 'field_officer'], true);
    }
}
