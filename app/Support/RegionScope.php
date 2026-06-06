<?php

namespace App\Support;

use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RegionScope
{
    /** @var array<int, array<int>> Request-level cache: user_id → region IDs */
    private static array $accessibleCache = [];

    /** @var array<int>|null Request-level cache for all region IDs */
    private static ?array $allRegionIdsCache = null;

    /** @var array<int, array<int>> Request-level cache: region_id -> self-to-root IDs */
    private static array $ancestorCache = [];

    /** @var bool|null Cached result of Schema::hasTable('user_region_scopes') */
    private static ?bool $hasUserRegionScopesTable = null;

    public static function shouldEnforceStrictScope(): bool
    {
        return (bool) config('app.strict_backoffice_region_scope', false);
    }

    public static function roleName(User $user): string
    {
        return strtolower((string) optional($user->role)->name);
    }

    public static function isSuperAdmin(User $user): bool
    {
        return self::roleName($user) === 'super_admin';
    }

    public static function isBackoffice(User $user): bool
    {
        return in_array(self::roleName($user), ['super_admin', 'admin', 'supporter', 'expert'], true);
    }

    public static function directRegionIds(User $user): array
    {
        $ids = [];

        if (! empty($user->region_id)) {
            $ids[] = (int) $user->region_id;
        }

        // Cache the Schema::hasTable result — it hits information_schema on every call otherwise.
        if (self::$hasUserRegionScopesTable === null) {
            self::$hasUserRegionScopesTable = Schema::hasTable('user_region_scopes');
        }

        if (self::$hasUserRegionScopesTable) {
            $extra = $user->scopedRegions()->pluck('regions.id')->all();
            foreach ($extra as $id) {
                if ($id !== null) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function expandWithDescendants(array $seedIds): array
    {
        $seedIds = array_values(array_unique(array_map('intval', array_filter($seedIds))));
        if ($seedIds === []) {
            return [];
        }

        // Single query: load the full parent→child map, then traverse in PHP.
        $rows = Region::query()
            ->whereNotNull('parent_id')
            ->select(['id', 'parent_id'])
            ->get();

        $childrenOf = [];
        foreach ($rows as $row) {
            $childrenOf[(int) $row->parent_id][] = (int) $row->id;
        }

        $all = array_flip($seedIds); // keyed set for O(1) membership checks
        $frontier = $seedIds;

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $parentId) {
                foreach ($childrenOf[$parentId] ?? [] as $childId) {
                    if (! isset($all[$childId])) {
                        $all[$childId] = true;
                        $next[] = $childId;
                    }
                }
            }
            $frontier = $next;
        }

        return array_values(array_keys($all));
    }

    public static function accessibleRegionIds(User $user, ?bool $strict = null): array
    {
        $strict = $strict ?? self::shouldEnforceStrictScope();
        $cacheKey = $user->id . ':' . ($strict ? '1' : '0');

        if (isset(self::$accessibleCache[$cacheKey])) {
            return self::$accessibleCache[$cacheKey];
        }

        if (self::isSuperAdmin($user)) {
            // Cache all-region IDs once per request
            if (self::$allRegionIdsCache === null) {
                self::$allRegionIdsCache = Region::query()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            return self::$accessibleCache[$cacheKey] = self::$allRegionIdsCache;
        }

        $direct = self::directRegionIds($user);
        if ($direct === [] && self::isBackoffice($user) && ! $strict) {
            if (self::$allRegionIdsCache === null) {
                self::$allRegionIdsCache = Region::query()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            return self::$accessibleCache[$cacheKey] = self::$allRegionIdsCache;
        }

        return self::$accessibleCache[$cacheKey] = self::expandWithDescendants($direct);
    }

    public static function canAccessRegion(User $user, ?int $regionId, ?bool $strict = null): bool
    {
        $strict = $strict ?? self::shouldEnforceStrictScope();

        if ($regionId === null) {
            return self::isBackoffice($user) && ! $strict;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        return in_array((int) $regionId, self::accessibleRegionIds($user, $strict), true);
    }

    public static function scopeMatchDistance(User $user, ?int $regionId): ?int
    {
        if ($regionId === null) {
            return null;
        }

        if (self::isSuperAdmin($user)) {
            return PHP_INT_MAX;
        }

        $ancestors = self::ancestorIds((int) $regionId);
        if ($ancestors === []) {
            return null;
        }

        $best = null;
        foreach (self::directRegionIds($user) as $seedId) {
            $distance = array_search((int) $seedId, $ancestors, true);
            if ($distance === false) {
                continue;
            }
            $best = $best === null ? (int) $distance : min($best, (int) $distance);
        }

        return $best;
    }

    /**
     * Returns [self, parent, grandparent, ...].
     *
     * @return array<int>
     */
    public static function ancestorIds(int $regionId): array
    {
        if (isset(self::$ancestorCache[$regionId])) {
            return self::$ancestorCache[$regionId];
        }

        $ancestors = [];
        $currentId = $regionId;
        while ($currentId > 0) {
            $region = Region::query()
                ->select(['id', 'parent_id'])
                ->find($currentId);

            if (! $region instanceof Region) {
                break;
            }

            $id = (int) $region->id;
            if (in_array($id, $ancestors, true)) {
                break;
            }

            $ancestors[] = $id;
            $currentId = (int) ($region->parent_id ?? 0);
        }

        return self::$ancestorCache[$regionId] = $ancestors;
    }

    /**
     * Clear the request-level cache (useful in tests or after user changes).
     */
    public static function flushCache(): void
    {
        self::$accessibleCache = [];
        self::$allRegionIdsCache = null;
        self::$ancestorCache = [];
        self::$hasUserRegionScopesTable = null;
    }
}
