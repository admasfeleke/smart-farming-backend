<?php

namespace App\Support;

use App\Models\User;

class AuthorityMatrix
{
    public static function can(User $user, string $action): bool
    {
        $matrix = (array) config('authority_matrix.actions', []);
        $ruleSet = (array) ($matrix[$action] ?? []);

        $role = RegionScope::roleName($user);
        $allowedLevels = $ruleSet[$role] ?? null;
        if (! is_array($allowedLevels) || $allowedLevels === []) {
            return false;
        }

        if (in_array('*', $allowedLevels, true)) {
            return true;
        }

        $level = self::normalizedLevel($user, $role);
        if ($level === null) {
            return false;
        }

        return in_array($level, $allowedLevels, true);
    }

    public static function canInRegion(User $user, string $action, ?int $regionId): bool
    {
        if (! self::can($user, $action)) {
            return false;
        }

        if (RegionScope::roleName($user) === 'farmer') {
            return true;
        }

        return RegionScope::canAccessRegion($user, $regionId);
    }

    private static function normalizedLevel(User $user, string $role): ?string
    {
        if ($role === 'super_admin') {
            return 'national';
        }

        $level = strtolower(trim((string) ($user->admin_level ?? '')));
        if ($level !== '') {
            return $level;
        }

        // Backward-compatible fallback: infer level from assigned region hierarchy when admin_level is not set.
        $regionLevel = strtolower(trim((string) optional($user->region)->level));
        if (in_array($regionLevel, ['region', 'zone', 'woreda', 'kebele'], true)) {
            return $regionLevel;
        }

        return null;
    }
}

