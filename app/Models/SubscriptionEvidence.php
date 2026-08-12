<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvidence extends Model
{
    protected $table = 'subscription_evidences';

    protected $fillable = [
        'subscription_id', 'renewal_decision_id', 'type', 'disk', 'path', 'original_name',
        'mime_type', 'size', 'sha256', 'uploaded_by',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
