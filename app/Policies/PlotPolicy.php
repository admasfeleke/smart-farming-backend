<?php

namespace App\Policies;

use App\Models\Plot;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class PlotPolicy
{
    private function isFarmer(User $user): bool
    {
        return RegionScope::roleName($user) === 'farmer';
    }

    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'plot.view_any');
    }

    public function view(User $user, Plot $plot): bool
    {
        if (! AuthorityMatrix::can($user, 'plot.view')) {
            return false;
        }

        if ($this->isFarmer($user)) {
            return $plot->farm?->farmer_id === $user->id;
        }

        return AuthorityMatrix::canInRegion($user, 'plot.view', $plot->farm?->region_id);
    }

    public function create(User $user): bool
    {
        // Plot management is owned by farmers only.
        return $this->isFarmer($user) && AuthorityMatrix::can($user, 'plot.create');
    }

    public function update(User $user, Plot $plot): bool
    {
        if (! $this->isFarmer($user) || ! AuthorityMatrix::can($user, 'plot.update')) {
            return false;
        }

        return $plot->farm?->farmer_id === $user->id;
    }

    public function delete(User $user, Plot $plot): bool
    {
        if (! $this->isFarmer($user) || ! AuthorityMatrix::can($user, 'plot.delete')) {
            return false;
        }

        return $plot->farm?->farmer_id === $user->id;
    }
}
