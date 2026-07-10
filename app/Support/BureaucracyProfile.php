<?php

namespace App\Support;

use App\Models\Region;
use App\Models\User;

class BureaucracyProfile
{
    public static function roleLabelFor(User|string|null $userOrRole): string
    {
        $role = $userOrRole instanceof User
            ? RegionScope::roleName($userOrRole)
            : strtolower(trim((string) $userOrRole));

        return match ($role) {
            'super_admin' => 'System Super Administrator',
            'admin' => 'Agriculture Office Coordinator',
            'supporter' => 'Development Agent (DA)',
            'expert' => 'Subject Matter Specialist (SMS)',
            'farmer' => 'Farmer',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    public static function displayTitleFor(User $user): string
    {
        $explicitTitle = trim((string) ($user->position_title ?? ''));
        if ($explicitTitle !== '') {
            return $explicitTitle;
        }

        $role = RegionScope::roleName($user);
        $level = self::effectiveLevel($user);
        $domain = trim((string) ($user->technical_domain ?? ''));
        if ($role === 'expert' && $domain !== '') {
            $domainTitle = config("central_ethiopia_bureaucracy.technical_domains.$domain");
            if (is_string($domainTitle) && $domainTitle !== '') {
                $levelLabel = (string) config("central_ethiopia_bureaucracy.levels.$level.label", ucfirst(str_replace('_', ' ', $level)));

                return $level === 'national' ? $domainTitle : $levelLabel.' '.$domainTitle;
            }
        }

        $titles = (array) config("central_ethiopia_bureaucracy.role_titles.$role", []);

        return (string) ($titles[$level] ?? $titles['own_farm'] ?? self::roleLabelFor($role));
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
