<?php

namespace App\Policies;

use App\Models\CaseAuditLog;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;

class CaseAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return AuthorityMatrix::can($user, 'case_audit_log.view_any');
    }

    public function view(User $user, CaseAuditLog $log): bool
    {
        if (! AuthorityMatrix::can($user, 'case_audit_log.view')) {
            return false;
        }

        return RegionScope::isSuperAdmin($user)
            || RegionScope::canAccessRegion($user, $log->actor_region_id);
    }
}
