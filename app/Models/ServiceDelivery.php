<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDelivery extends Model
{
    protected $fillable = ['service_request_id', 'status', 'reference', 'access_url', 'starts_on', 'ends_on', 'metadata'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'metadata' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }
}
