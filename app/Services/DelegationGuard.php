<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Region;
use App\Models\User;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DelegationGuard
{
    public function scopeManageableUsersQuery(Builder $query, User $actor): Builder
    {
        if (RegionScope::isSuperAdmin($actor)) {
            return $query;
        }

        if (RegionScope::roleName($actor) === 'admin') {
            $regions = RegionScope::accessibleRegionIds($actor);
            if ($regions === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query
                ->whereHas('role', fn (Builder $roleQuery) => $roleQuery->whereNotIn('name', ['super_admin', 'admin']))
                ->where(function (Builder $scopeQuery) use ($regions): void {
                    $scopeQuery
                        ->whereIn('region_id', $regions)
                        ->orWhereHas('scopedRegions', fn (Builder $r) => $r->whereIn('regions.id', $regions));
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function availableRoleOptions(User $actor): array
    {
        $query = Role::query()->orderBy('name');

        if (RegionScope::isSuperAdmin($actor)) {
            return $query->pluck('name', 'id')->all();
        }

        if (RegionScope::roleName($actor) === 'admin') {
            return $query
                ->whereIn('name', ['supporter', 'expert', 'farmer'])
                ->pluck('name', 'id')
                ->all();
        }

        return [];
    }

    public function availablePrimaryRegionOptions(User $actor, ?string $roleName = null, ?string $adminLevel = null): array
    {
        $query = Region::query()
            ->where('is_active', 1)
            ->orderByRaw("FIELD(level, 'region', 'zone', 'woreda', 'kebele')")
            ->orderBy('name')
            ->select(['id', 'name', 'level']);

        $normalizedRole = strtolower(trim((string) $roleName));
        $normalizedLevel = strtolower(trim((string) $adminLevel));
        if (in_array($normalizedRole, ['super_admin', 'admin', 'supporter', 'expert'], true)) {
            if ($normalizedLevel === '') {
                return [];
            }
            $query->where('level', $normalizedLevel);
        }

        if (! RegionScope::isSuperAdmin($actor)) {
            $regionIds = RegionScope::accessibleRegionIds($actor);
            if ($regionIds === []) {
                return [];
            }
            $query->whereIn('id', $regionIds);
        }

        return $query->get()
            ->mapWithKeys(fn (Region $region): array => [
                $region->id => sprintf('%s (%s)', $region->name, strtoupper((string) $region->level)),
            ])
            ->all();
    }

    public function availableScopedRegionOptions(User $actor, int $primaryRegionId): array
    {
        if ($primaryRegionId <= 0) {
            return [];
        }

        $descendants = RegionScope::expandWithDescendants([$primaryRegionId]);
        $allowed = array_values(array_diff($descendants, [$primaryRegionId]));
        if ($allowed === []) {
            return [];
        }

        if (! RegionScope::isSuperAdmin($actor)) {
            $actorScope = RegionScope::accessibleRegionIds($actor);
            $allowed = array_values(array_intersect($allowed, $actorScope));
            if ($allowed === []) {
                return [];
            }
        }

        return Region::query()
            ->whereIn('id', $allowed)
            ->orderByRaw("FIELD(level, 'region', 'zone', 'woreda', 'kebele')")
            ->orderBy('name')
            ->get(['id', 'name', 'level'])
            ->mapWithKeys(fn (Region $region): array => [
                $region->id => sprintf('%s (%s)', $region->name, strtoupper((string) $region->level)),
            ])
            ->all();
    }

    public function roleNameById(int $roleId): ?string
    {
        if ($roleId <= 0) {
            return null;
        }

        $role = Role::query()->find($roleId);

        return $role ? strtolower((string) $role->name) : null;
    }

    public function canCreate(User $actor): bool
    {
        return AuthorityMatrix::can($actor, 'delegation.manage');
    }

    public function canEdit(User $actor, User $record): bool
    {
        if (! AuthorityMatrix::can($actor, 'delegation.manage')) {
            return false;
        }

        if (RegionScope::isSuperAdmin($actor)) {
            return true;
        }

        if (RegionScope::roleName($actor) !== 'admin') {
            return false;
        }

        $targetRole = strtolower((string) optional($record->role)->name);
        if (in_array($targetRole, ['super_admin', 'admin'], true)) {
            return false;
        }

        return $this->recordWithinActorScope($record, $actor);
    }

    public function canDelete(User $actor, User $record): bool
    {
        if (! $this->canEdit($actor, $record)) {
            return false;
        }

        if ((int) $actor->id === (int) $record->id) {
            return false;
        }

        if ($this->isLastActiveSuperAdmin($record)) {
            return false;
        }

        return true;
    }

    public function guardDelegation(User $actor, array $data, ?User $target = null): array
    {
        if (! AuthorityMatrix::can($actor, 'delegation.manage')) {
            throw ValidationException::withMessages([
                'role_id' => ['You are not allowed to delegate user roles.'],
            ]);
        }

        $actorRole = RegionScope::roleName($actor);
        if (! in_array($actorRole, ['super_admin', 'admin'], true)) {
            throw ValidationException::withMessages([
                'role_id' => ['Only super admin or admin can delegate roles.'],
            ]);
        }

        $roleId = (int) ($data['role_id'] ?? $target?->role_id ?? 0);
        $assignedRole = Role::query()->find($roleId);
        if (! $assignedRole) {
            throw ValidationException::withMessages([
                'role_id' => ['Selected role is invalid.'],
            ]);
        }
        $assignedRoleName = strtolower((string) $assignedRole->name);

        if ($target && ! $this->canEdit($actor, $target)) {
            throw ValidationException::withMessages([
                'role_id' => ['You are not allowed to modify this user.'],
            ]);
        }

        if ($target && (int) $target->id === (int) $actor->id) {
            $nextIsActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $target->is_active;
            if (! $nextIsActive) {
                throw ValidationException::withMessages([
                    'is_active' => ['You cannot deactivate your own account.'],
                ]);
            }

            if (RegionScope::roleName($target) === 'super_admin' && $assignedRoleName !== 'super_admin') {
                throw ValidationException::withMessages([
                    'role_id' => ['You cannot demote your own super admin account.'],
                ]);
            }
        }

        if ($actorRole === 'admin') {
            if (in_array($assignedRoleName, ['super_admin', 'admin'], true)) {
                throw ValidationException::withMessages([
                    'role_id' => ['Admin can only delegate farmer, supporter, or expert roles.'],
                ]);
            }

            $accessible = RegionScope::accessibleRegionIds($actor);
            if ($accessible === []) {
                throw ValidationException::withMessages([
                    'region_id' => ['Your account has no delegated region scope.'],
                ]);
            }

            $regionId = isset($data['region_id']) ? (int) $data['region_id'] : (int) ($target?->region_id ?? 0);
            if ($regionId > 0 && ! in_array($regionId, $accessible, true)) {
                throw ValidationException::withMessages([
                    'region_id' => ['Selected primary region is outside your delegated scope.'],
                ]);
            }

            $scopedRegions = array_map('intval', (array) ($data['scopedRegions'] ?? []));
            $outOfScope = array_values(array_filter($scopedRegions, fn (int $id): bool => ! in_array($id, $accessible, true)));
            if ($outOfScope !== []) {
                throw ValidationException::withMessages([
                    'scopedRegions' => ['One or more additional scopes are outside your delegated region authority.'],
                ]);
            }

            if (isset($data['admin_level']) && filled($data['admin_level'])) {
                $actorRank = $this->adminLevelRank((string) ($actor->admin_level ?? ''));
                $delegateRank = $this->adminLevelRank((string) $data['admin_level']);
                if ($actorRank !== null && $delegateRank !== null && $delegateRank < $actorRank) {
                    throw ValidationException::withMessages([
                        'admin_level' => ['Delegated admin level cannot be broader than your own level.'],
                    ]);
                }
            }
        }

        $nextIsActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($target?->is_active ?? true);
        if ($target && RegionScope::roleName($target) === 'super_admin') {
            if ($assignedRoleName !== 'super_admin' || ! $nextIsActive) {
                if ($this->isLastActiveSuperAdmin($target)) {
                    throw ValidationException::withMessages([
                        'role_id' => ['At least one active super admin account must remain.'],
                    ]);
                }
            }
        }

        if ($assignedRoleName === 'super_admin') {
            $data['admin_level'] = 'national';
            $data['region_id'] = null;
            $data['scopedRegions'] = [];
        } elseif (in_array($assignedRoleName, ['admin', 'supporter', 'expert'], true)) {
            if (empty($data['admin_level'])) {
                throw ValidationException::withMessages([
                    'admin_level' => ['Admin level is required for backoffice roles.'],
                ]);
            }
            if (empty($data['region_id'])) {
                throw ValidationException::withMessages([
                    'region_id' => ['Primary region is required for backoffice roles.'],
                ]);
            }

            $this->assertPrimaryRegionMatchesAdminLevel((int) $data['region_id'], (string) $data['admin_level']);

            $primaryRegionId = isset($data['region_id']) ? (int) $data['region_id'] : (int) ($target?->region_id ?? 0);
            $scopedRegions = array_values(array_unique(array_map('intval', (array) ($data['scopedRegions'] ?? []))));
            $this->assertScopedRegionsWithinPrimary($primaryRegionId, $scopedRegions);
            $data['scopedRegions'] = $scopedRegions;
        } else {
            $data['admin_level'] = null;
            $data['scopedRegions'] = [];
        }

        return $data;
    }

    public function assertCanDeleteRecord(User $actor, User $record): void
    {
        if (! $this->canDelete($actor, $record)) {
            throw ValidationException::withMessages([
                'name' => ['You are not allowed to delete this user.'],
            ]);
        }

        if ((int) $actor->id === (int) $record->id) {
            throw ValidationException::withMessages([
                'name' => ['You cannot delete your own account.'],
            ]);
        }

        if ($this->isLastActiveSuperAdmin($record)) {
            throw ValidationException::withMessages([
                'name' => ['Cannot delete the last active super admin account.'],
            ]);
        }
    }

    private function recordWithinActorScope(User $record, User $actor): bool
    {
        $accessible = RegionScope::accessibleRegionIds($actor);
        if ($accessible === []) {
            return false;
        }

        if (! empty($record->region_id) && in_array((int) $record->region_id, $accessible, true)) {
            return true;
        }

        $extra = $record->scopedRegions()->pluck('regions.id')->map(fn ($id) => (int) $id)->all();
        foreach ($extra as $id) {
            if (in_array($id, $accessible, true)) {
                return true;
            }
        }

        return false;
    }

    private function adminLevelRank(string $level): ?int
    {
        return match (strtolower(trim($level))) {
            'national' => 1,
            'region' => 2,
            'zone' => 3,
            'woreda' => 4,
            'kebele' => 5,
            default => null,
        };
    }

    private function assertScopedRegionsWithinPrimary(int $primaryRegionId, array $scopedRegionIds): void
    {
        if ($scopedRegionIds === []) {
            return;
        }

        if ($primaryRegionId <= 0) {
            throw ValidationException::withMessages([
                'scopedRegions' => ['Primary region is required before assigning additional scopes.'],
            ]);
        }

        $allowed = RegionScope::expandWithDescendants([$primaryRegionId]);
        $allowed = array_values(array_diff($allowed, [$primaryRegionId]));

        $invalid = array_values(array_filter(
            $scopedRegionIds,
            fn (int $id): bool => ! in_array($id, $allowed, true)
        ));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'scopedRegions' => ['Additional region scopes must belong to the selected primary region subtree.'],
            ]);
        }
    }

    private function assertPrimaryRegionMatchesAdminLevel(int $primaryRegionId, string $adminLevel): void
    {
        if ($primaryRegionId <= 0) {
            return;
        }

        $region = Region::query()->find($primaryRegionId);
        if (! $region) {
            throw ValidationException::withMessages([
                'region_id' => ['Selected primary region is invalid.'],
            ]);
        }

        $expectedLevel = strtolower(trim($adminLevel));
        $actualLevel = strtolower(trim((string) $region->level));
        if ($expectedLevel === '' || $actualLevel === '') {
            return;
        }

        if ($actualLevel !== $expectedLevel) {
            throw ValidationException::withMessages([
                'region_id' => ['Primary region level must match selected admin level.'],
            ]);
        }
    }

    private function isLastActiveSuperAdmin(User $target): bool
    {
        $targetRole = strtolower((string) optional($target->role)->name);
        if ($targetRole === '' && ! empty($target->role_id)) {
            $targetRole = $this->roleNameById((int) $target->role_id) ?? '';
        }

        if ($targetRole !== 'super_admin' || ! $target->is_active) {
            return false;
        }

        return User::query()
            ->whereKeyNot($target->id)
            ->where('is_active', 1)
            ->whereHas('role', fn (Builder $q) => $q->where('name', 'super_admin'))
            ->count() === 0;
    }
}
