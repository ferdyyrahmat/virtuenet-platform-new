<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['repo_full_name', 'status', 'http_status', 'response_time_ms', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }
}
