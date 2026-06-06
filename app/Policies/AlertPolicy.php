<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class AlertPolicy
{
    private function roleName(User $user): string
    {
        return RegionScope::roleName($user);
    }

    private function isFarmer(User $user): bool
    {
        return $this->roleName($user) === 'farmer';
    }

    private function isManagement(User $user): bool
    {
        return in_array($this->roleName($user), ['super_admin', 'admin', 'expert', 'supporter'], true);
    }

    private function alertRegionId(Alert $alert): ?int
    {
        return $alert->diseaseReport?->plot?->farm?->region_id
            ?? $alert->farm?->region_id
            ?? $alert->plot?->farm?->region_id
            ?? $alert->planting?->plot?->farm?->region_id;
    }

    private function alertFarmerId(Alert $alert): ?int
    {
        return $alert->diseaseReport?->plot?->farm?->farmer_id
            ?? $alert->farm?->farmer_id
            ?? $alert->plot?->farm?->farmer_id
            ?? $alert->planting?->plot?->farm?->farmer_id;
    }

    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'alert.view_any');
    }

    public function view(User $user, Alert $alert): bool
    {
        if (! AuthorityMatrix::can($user, 'alert.view')) {
            return false;
        }

        if ($this->isManagement($user)) {
            return AuthorityMatrix::canInRegion($user, 'alert.view', $this->alertRegionId($alert));
        }

        return $this->isFarmer($user)
            && $this->alertFarmerId($alert) === $user->id;
    }

    public function update(User $user, Alert $alert): bool
    {
        if (! AuthorityMatrix::can($user, 'alert.update')) {
            return false;
        }

        if ($this->isManagement($user)) {
            return AuthorityMatrix::canInRegion($user, 'alert.update', $this->alertRegionId($alert));
        }

        return $this->isFarmer($user)
            && $this->alertFarmerId($alert) === $user->id;
    }

    public function create(User $user): bool
    {
        return AuthorityMatrix::can($user, 'alert.create');
    }
}
