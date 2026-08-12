<?php

namespace App\Models;

use App\Enums\ServiceRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestTemplate extends Model
{
    protected $fillable = ['request_type', 'version', 'name', 'summary', 'defaults', 'active', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['request_type' => ServiceRequestType::class, 'defaults' => 'array', 'active' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
