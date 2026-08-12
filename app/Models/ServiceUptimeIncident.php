<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUptimeIncident extends Model
{
    protected $fillable = ['repo_full_name', 'started_at', 'ended_at', 'notified_at', 'duration_seconds', 'last_status'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DeployedApplication::class, 'repo_full_name', 'repo_full_name');
    }
}
