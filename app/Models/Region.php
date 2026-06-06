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
    public const LEVEL_WOREDA = 'woreda';
    public const LEVEL_KEBELE = 'kebele';

    public const LEVELS = [
        self::LEVEL_REGION,
        self::LEVEL_ZONE,
        self::LEVEL_WOREDA,
        self::LEVEL_KEBELE,
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
                    'level' => ['Invalid level. Allowed: region, zone, woreda, kebele.'],
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

            $expected = self::expectedChildLevel($parent->level);
            if ($expected === null || $region->level !== $expected) {
                throw ValidationException::withMessages([
                    'level' => ["Invalid hierarchy. A {$parent->level} can only have {$expected} children."],
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
        return match (strtolower((string) $parentLevel)) {
            self::LEVEL_REGION => self::LEVEL_ZONE,
            self::LEVEL_ZONE => self::LEVEL_WOREDA,
            self::LEVEL_WOREDA => self::LEVEL_KEBELE,
            default => null,
        };
    }

    public static function expectedParentLevel(?string $level): ?string
    {
        return match (strtolower((string) $level)) {
            self::LEVEL_ZONE => self::LEVEL_REGION,
            self::LEVEL_WOREDA => self::LEVEL_ZONE,
            self::LEVEL_KEBELE => self::LEVEL_WOREDA,
            default => null,
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
