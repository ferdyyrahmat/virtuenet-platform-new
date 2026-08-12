<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpsNode extends Model
{
    protected $attributes = [
        'account_type' => 'it_shared',
        'active' => true,
    ];

    protected $fillable = ['legacy_uuid', 'name', 'cluster_key', 'hostname', 'ip_address', 'account_type', 'department_id', 'coolify_server_uuid', 'cpu_cores', 'ram_gb', 'disk_gb', 'cpu_usage_percent', 'ram_usage_percent', 'disk_usage_percent', 'last_reported_at', 'active', 'notes'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_reported_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(DeployedApplication::class);
    }
}
