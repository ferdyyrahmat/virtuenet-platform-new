<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestUpdate extends Model
{
    protected $fillable = ['service_request_id', 'actor_id', 'type', 'message', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
