<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['subscription_id', 'from_status', 'to_status', 'actor_id', 'reason', 'evidence_reference', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
