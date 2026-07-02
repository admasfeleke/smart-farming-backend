<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class Region extends Model
{
    use HasFactory;

    public const LEVEL_REGION = 'region';
    public const LEVEL_ZONE = 'zone';
    public const LEVEL_SPECIAL_WOREDA = 'special_woreda';
    public const LEVEL_WOREDA = 'woreda';
    public const LEVEL_KEBELE = 'kebele';
    public const LEVEL_FTC = 'ftc';

    public const LEVELS = [
        self::LEVEL_REGION,
        self::LEVEL_ZONE,
        self::LEVEL_SPECIAL_WOREDA,
        self::LEVEL_WOREDA,
        self::LEVEL_KEBELE,
        self::LEVEL_FTC,
    ];

    protected $fillable = [
        'name',
        'parent_id',
        'level',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (Region $region): void {
            $region->level = strtolower((string) $region->level);

            if (! in_array($region->level, self::LEVELS, true)) {
                throw ValidationException::withMessages([
                    'level' => ['Invalid level. Allowed: region, zone, special_woreda, woreda, kebele, ftc.'],
                ]);
            }

            if ($region->level === self::LEVEL_REGION) {
                if ($region->parent_id !== null) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['A region cannot have a parent.'],
                    ]);
                }
                return;
            }

            if ($region->parent_id === null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['This level requires a parent.'],
                ]);
            }

            $parent = self::query()->find($region->parent_id);
            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Selected parent region was not found.'],
                ]);
            }

            $expected = self::expectedChildLevels($parent->level);
            if ($expected === [] || ! in_array($region->level, $expected, true)) {
                throw ValidationException::withMessages([
                    'level' => ["Invalid hierarchy. A {$parent->level} can only have ".implode(' or ', $expected).' children.'],
                ]);
            }

            if ($region->exists && (int) $parent->id === (int) $region->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A region cannot be parent of itself.'],
                ]);
            }

            // Detect parent cycles.
            $visited = [$region->id];
            $cursor = $parent;
            while ($cursor) {
                if (in_array($cursor->id, $visited, true)) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['Invalid hierarchy cycle detected.'],
                    ]);
                }
                $visited[] = $cursor->id;
                $cursor = $cursor->parent;
            }
        });
    }

    public static function expectedChildLevel(?string $parentLevel): ?string
    {
        $levels = self::expectedChildLevels($parentLevel);

        return $levels[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function expectedChildLevels(?string $parentLevel): array
    {
        return match (strtolower((string) $parentLevel)) {
            self::LEVEL_REGION => [self::LEVEL_ZONE, self::LEVEL_SPECIAL_WOREDA],
            self::LEVEL_ZONE => [self::LEVEL_WOREDA],
            self::LEVEL_SPECIAL_WOREDA, self::LEVEL_WOREDA => [self::LEVEL_KEBELE, self::LEVEL_FTC],
            default => [],
        };
    }

    public static function expectedParentLevel(?string $level): ?string
    {
        $levels = self::expectedParentLevels($level);

        return $levels[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function expectedParentLevels(?string $level): array
    {
        return match (strtolower((string) $level)) {
            self::LEVEL_ZONE, self::LEVEL_SPECIAL_WOREDA => [self::LEVEL_REGION],
            self::LEVEL_WOREDA => [self::LEVEL_ZONE],
            self::LEVEL_KEBELE, self::LEVEL_FTC => [self::LEVEL_WOREDA, self::LEVEL_SPECIAL_WOREDA],
            default => [],
        };
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
