<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinancialEntry extends Model
{
    use LogsActivity;

    protected $fillable = [
        'reference', 'kind', 'status', 'subscription_id', 'ai_credential_id', 'application_repo_full_name',
        'service_request_id', 'payment_instrument_id', 'department_id', 'owner_id', 'cost_center', 'vendor',
        'accounting_period', 'occurred_on', 'original_amount', 'currency', 'normalized_idr', 'fx_rate',
        'fx_source', 'fx_effective_at', 'normalization_method', 'evidence_reference', 'correction_of_id',
        'source_key', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'accounting_period' => 'date', 'occurred_on' => 'date', 'original_amount' => 'decimal:4',
            'normalized_idr' => 'decimal:2', 'fx_rate' => 'decimal:8', 'fx_effective_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Financial entries are immutable. Create a correction entry.'));
        static::deleting(fn () => throw new \LogicException('Financial entries cannot be deleted.'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('financial_entry')->logOnlyDirty()->logFillable();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paymentInstrument(): BelongsTo
    {
        return $this->belongsTo(PaymentInstrument::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(StatementLine::class);
    }
}
