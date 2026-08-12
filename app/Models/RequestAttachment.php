<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAttachment extends Model
{
    protected $fillable = ['service_request_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }
}
