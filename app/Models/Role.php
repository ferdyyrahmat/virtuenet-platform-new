<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public const DEVELOPER = 'Developer';

    public const ADMIN = 'Admin';

    public const USER = 'User';

    protected $fillable = [
        'name',
        'description',
        'guard_name',
        'is_locked',
    ];

    protected $casts = ['is_locked' => 'boolean'];

    public function isLocked(): bool
    {
        return (bool) $this->is_locked || $this->isDeveloper();
    }

    public function isDeveloper(): bool
    {
        return strcasecmp($this->name, self::DEVELOPER) === 0;
    }

    public function scopeDeveloper(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(name) = ?', [mb_strtolower(self::DEVELOPER)]);
    }

    public function scopeExceptDeveloper(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(name) <> ?', [mb_strtolower(self::DEVELOPER)]);
    }
}
