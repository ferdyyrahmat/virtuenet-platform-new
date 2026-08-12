<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployedApplication extends Model
{
    protected $table = 'github_deployed_repos';

    protected $primaryKey = 'repo_full_name';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $keyType = 'string';

    protected $fillable = ['repo_full_name', 'display_name', 'domain', 'sync_enabled', 'backup_enabled', 'notes', 'department_id', 'cost_center', 'health_status', 'http_status', 'response_time_ms', 'last_checked_at'];

    protected function casts(): array
    {
        return ['sync_enabled' => 'boolean', 'backup_enabled' => 'boolean', 'last_checked_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
