<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'description',
        'guard_name',
        'is_locked',
    ];

    protected $casts = ['is_locked' => 'boolean'];

    public function isLocked(): bool
    {
        return (bool) $this->is_locked || strcasecmp($this->name, 'Developer') === 0;
    }
}
