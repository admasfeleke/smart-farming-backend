<?php

namespace App\Support;

use App\Models\Region;
use App\Models\User;

class BureaucracyProfile
{
    public static function displayTitleFor(User $user): string
    {
        $role = RegionScope::roleName($user);
        $level = self::effectiveLevel($user);
        $titles = (array) config("central_ethiopia_bureaucracy.role_titles.$role", []);

        return (string) ($titles[$level] ?? $titles['own_farm'] ?? ucfirst(str_replace('_', ' ', $role)));
    }

    public static function officeFor(?Region $region): ?string
    {
        if (! $region) {
            return null;
        }

        $level = strtolower((string) $region->level);

        return config("central_ethiopia_bureaucracy.levels.$level.agriculture_office");
    }

    public static function effectiveLevel(User $user): string
    {
        if (RegionScope::isSuperAdmin($user)) {
            return 'national';
        }

        $regionLevel = strtolower((string) optional($user->region)->level);
        if (in_array($regionLevel, ['special_woreda', 'ftc'], true)) {
            return $regionLevel;
        }

        $adminLevel = strtolower(trim((string) ($user->admin_level ?? '')));
        if ($adminLevel !== '') {
            return $adminLevel;
        }

        return $regionLevel !== '' ? $regionLevel : 'own_farm';
    }
}
