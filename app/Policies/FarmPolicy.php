<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class FarmPolicy
{
    private function isFarmer(User $user): bool
    {
        return RegionScope::roleName($user) === 'farmer';
    }

    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'farm.view_any');
    }

    public function view(User $user, Farm $farm): bool
    {
        if (! AuthorityMatrix::can($user, 'farm.view')) {
            return false;
        }

        if ($this->isFarmer($user)) {
            return $farm->farmer_id === $user->id;
        }

        return AuthorityMatrix::canInRegion($user, 'farm.view', $farm->region_id);
    }

    public function create(User $user): bool
    {
        // Farm structure management is owned by farmers only.
        return $this->isFarmer($user) && AuthorityMatrix::can($user, 'farm.create');
    }

    public function update(User $user, Farm $farm): bool
    {
        if (! $this->isFarmer($user) || ! AuthorityMatrix::can($user, 'farm.update')) {
            return false;
        }

        return $farm->farmer_id === $user->id;
    }

    public function delete(User $user, Farm $farm): bool
    {
        if (! $this->isFarmer($user) || ! AuthorityMatrix::can($user, 'farm.delete')) {
            return false;
        }

        return $farm->farmer_id === $user->id;
    }
}
