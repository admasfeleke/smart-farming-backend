<?php

namespace App\Policies;

use App\Models\Planting;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class PlantingPolicy
{
    private function isFarmer(User $user): bool
    {
        return RegionScope::roleName($user) === 'farmer';
    }

    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'planting.view_any');
    }

    public function view(User $user, Planting $planting): bool
    {
        if (! AuthorityMatrix::can($user, 'planting.view')) {
            return false;
        }

        if ($this->isFarmer($user)) {
            return $planting->plot?->farm?->farmer_id === $user->id;
        }

        return AuthorityMatrix::canInRegion($user, 'planting.view', $planting->plot?->farm?->region_id);
    }

    public function create(User $user): bool
    {
        // Planting management is owned by farmers only.
        return $this->isFarmer($user) && AuthorityMatrix::can($user, 'planting.create');
    }

    public function update(User $user, Planting $planting): bool
    {
        if (! $this->isFarmer($user) || ! AuthorityMatrix::can($user, 'planting.update')) {
            return false;
        }

        return $planting->plot?->farm?->farmer_id === $user->id;
    }

    public function delete(User $user, Planting $planting): bool
    {
        if (! $this->isFarmer($user) || ! AuthorityMatrix::can($user, 'planting.delete')) {
            return false;
        }

        return $planting->plot?->farm?->farmer_id === $user->id;
    }
}
