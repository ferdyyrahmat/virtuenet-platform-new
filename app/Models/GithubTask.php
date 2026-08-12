<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GithubTask extends Model
{
    protected $fillable = ['repository', 'issue_number', 'github_node_id', 'department_id', 'title', 'body', 'state', 'labels', 'assignee', 'mandays', 'github_url', 'lark_task_id', 'lark_task_url', 'sync_status', 'sync_error', 'remote_updated_at', 'last_synced_at'];

    protected function casts(): array
    {
        return ['labels' => 'array', 'mandays' => 'decimal:2', 'remote_updated_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(GithubLarkTaskMapping::class, 'github_issue_url', 'github_url');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
