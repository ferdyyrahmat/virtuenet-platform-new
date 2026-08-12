<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionReminder extends Model
{
    protected $fillable = [
        'subscription_id', 'checkpoint', 'renewal_date', 'due_at', 'delivery_state', 'channel',
        'delivered_at', 'escalated_at', 'acknowledged_at', 'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'renewal_date' => 'date', 'due_at' => 'datetime', 'delivered_at' => 'datetime',
            'escalated_at' => 'datetime', 'acknowledged_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
