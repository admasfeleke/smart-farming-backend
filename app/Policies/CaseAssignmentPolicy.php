<?php

namespace App\Policies;

use App\Models\CaseAssignment;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class CaseAssignmentPolicy
{
    private function isSupportRole(User $user): bool
    {
        return in_array(RegionScope::roleName($user), ['supporter', 'expert'], true);
    }

    private function isAdminRole(User $user): bool
    {
        return in_array(RegionScope::roleName($user), ['super_admin', 'admin'], true);
    }

    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'case_assignment.view_any');
    }

    public function view(User $user, CaseAssignment $assignment): bool
    {
        if (! AuthorityMatrix::can($user, 'case_assignment.view')) {
            return false;
        }

        if ($this->isAdminRole($user)) {
            return AuthorityMatrix::canInRegion($user, 'case_assignment.view', $assignment->caseRegionId());
        }

        if ($this->isSupportRole($user)) {
            return (int) $assignment->assigned_to_user_id === (int) $user->id;
        }

        return false;
    }

    public function update(User $user, CaseAssignment $assignment): bool
    {
        if (! AuthorityMatrix::can($user, 'case_assignment.update')) {
            return false;
        }

        return $this->view($user, $assignment);
    }
}
