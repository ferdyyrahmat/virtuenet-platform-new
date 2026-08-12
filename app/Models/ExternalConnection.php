<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalConnection extends Model
{
    protected $fillable = ['provider', 'label', 'base_url', 'credentials', 'settings', 'enabled', 'health_status', 'last_error', 'last_checked_at'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'settings' => 'array', 'enabled' => 'boolean', 'last_checked_at' => 'datetime'];
    }
}
