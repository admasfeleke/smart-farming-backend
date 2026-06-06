<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    // ---------- HELPER METHODS FOR COMMON ROLES ----------

    /** @var array<string, self> In-memory cache per request */
    private static array $cache = [];

    private static function findByName(string $name): self
    {
        return self::$cache[$name] ??= static::where('name', $name)->firstOrFail();
    }

    public static function farmer(): self
    {
        return self::findByName('farmer');
    }

    public static function admin(): self
    {
        return self::findByName('admin');
    }

    public static function superAdmin(): self
    {
        return self::findByName('super_admin');
    }

    public static function supporter(): self
    {
        return self::findByName('supporter');
    }

    public static function expert(): self
    {
        return self::findByName('expert');
    }
}
