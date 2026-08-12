<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestApproval extends Model
{
    protected $fillable = ['service_request_id', 'round', 'step', 'stage', 'approver_id', 'status', 'note', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
