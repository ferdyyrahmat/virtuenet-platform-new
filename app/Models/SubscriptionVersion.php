<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SubscriptionVersion extends Model
{
    protected $fillable = [
        'subscription_id', 'version', 'status', 'quantity', 'unit_price', 'subtotal', 'discount', 'tax',
        'fee', 'currency', 'normalized_idr', 'fx_rate', 'fx_source', 'fx_effective_at', 'billing_cycle',
        'billing_interval_months', 'effective_from', 'reason', 'evidence_reference', 'proposed_by',
        'checked_by', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4', 'subtotal' => 'decimal:4', 'discount' => 'decimal:4',
            'tax' => 'decimal:4', 'fee' => 'decimal:4', 'normalized_idr' => 'decimal:2',
            'fx_rate' => 'decimal:8', 'fx_effective_at' => 'datetime', 'effective_from' => 'date',
            'checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') === 'approved') {
                throw ValidationException::withMessages(['version' => 'Approved commercial versions are immutable. Create a new version instead.']);
            }
        });
        static::deleting(function (self $version): void {
            if ($version->status === 'approved') {
                throw ValidationException::withMessages(['version' => 'Approved commercial versions cannot be deleted.']);
            }
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
