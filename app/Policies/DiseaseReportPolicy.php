<?php

namespace App\Policies;

use App\Models\DiseaseReport;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class DiseaseReportPolicy
{
    private function isSupportRole(User $user): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin', 'expert', 'supporter'], true);
    }

    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'disease_report.view_any');
    }

    public function view(User $user, DiseaseReport $diseaseReport): bool
    {
        if (! AuthorityMatrix::can($user, 'disease_report.view')) {
            return false;
        }

        if ($this->isSupportRole($user)) {
            return AuthorityMatrix::canInRegion($user, 'disease_report.view', $diseaseReport->plot?->farm?->region_id);
        }

        return $diseaseReport->plot?->farm?->farmer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return AuthorityMatrix::can($user, 'disease_report.create');
    }

    public function verify(User $user, DiseaseReport $diseaseReport): bool
    {
        return AuthorityMatrix::canInRegion($user, 'disease_report.verify', $diseaseReport->plot?->farm?->region_id);
    }
}
