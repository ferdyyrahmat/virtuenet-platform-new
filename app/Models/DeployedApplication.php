<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeployedApplication extends Model
{
    protected $attributes = [
        'environment' => 'virtuenet',
        'health_status' => 'unknown',
        'health_path' => '/health',
        'runtime_status' => 'unknown',
        'sync_enabled' => false,
        'backup_enabled' => true,
    ];

    protected $table = 'github_deployed_repos';

    protected $primaryKey = 'repo_full_name';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $keyType = 'string';

    protected $fillable = ['repo_full_name', 'display_name', 'domain', 'coolify_uuid', 'environment', 'sync_enabled', 'backup_enabled', 'notes', 'department_id', 'vps_node_id', 'owner_id', 'cost_center', 'domain_exception_reason', 'health_status', 'health_path', 'runtime_status', 'thumbnail_url', 'http_status', 'response_time_ms', 'last_checked_at', 'deployed_at', 'offline_since', 'last_recovered_at'];

    protected function casts(): array
    {
        return ['sync_enabled' => 'boolean', 'backup_enabled' => 'boolean', 'last_checked_at' => 'datetime', 'deployed_at' => 'datetime', 'offline_since' => 'datetime', 'last_recovered_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(VpsNode::class, 'vps_node_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ServiceHealthCheck::class, 'repo_full_name', 'repo_full_name');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ServiceUptimeIncident::class, 'repo_full_name', 'repo_full_name');
    }
}
