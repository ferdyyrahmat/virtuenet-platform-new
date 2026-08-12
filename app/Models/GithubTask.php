<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubTask extends Model
{
    protected $fillable = ['repository', 'issue_number', 'github_node_id', 'title', 'body', 'state', 'labels', 'assignee', 'github_url', 'lark_task_id', 'lark_task_url', 'sync_status', 'sync_error', 'remote_updated_at', 'last_synced_at'];

    protected function casts(): array
    {
        return ['labels' => 'array', 'remote_updated_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }
}
