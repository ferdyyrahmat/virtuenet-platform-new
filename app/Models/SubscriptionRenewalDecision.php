<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRenewalDecision extends Model
{
    protected $fillable = [
        'subscription_id', 'current_version_id', 'proposed_version_id', 'decision', 'status',
        'renewal_date', 'next_renewal_date', 'final_service_date', 'decision_due_at', 'reason', 'evidence_reference',
        'made_by', 'checked_by', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'renewal_date' => 'date', 'next_renewal_date' => 'date', 'final_service_date' => 'date',
            'decision_due_at' => 'datetime', 'checked_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function proposedVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionVersion::class, 'proposed_version_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_by');
    }
}
