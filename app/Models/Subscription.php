<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subscription extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'service_request_id', 'vendor', 'product', 'plan', 'category', 'status', 'description',
        'business_purpose', 'account_identifier', 'account_fingerprint', 'identity_fingerprint', 'secret_reference',
        'owner_id', 'renewal_owner_id', 'department_id', 'payment_instrument_id', 'current_version_id',
        'cost_center', 'project_reference', 'billing_cycle', 'billing_interval_months', 'start_date',
        'next_renewal_date', 'contract_end_date', 'cancellation_deadline', 'grace_days', 'auto_renew',
        'vendor_portal_url', 'beneficiary_notes', 'tags', 'reminder_days', 'source', 'created_by', 'updated_by',
    ];

    protected $hidden = ['account_identifier', 'account_fingerprint', 'identity_fingerprint'];

    protected function casts(): array
    {
        return [
            'account_identifier' => 'encrypted',
            'start_date' => 'date',
            'next_renewal_date' => 'date',
            'contract_end_date' => 'date',
            'cancellation_deadline' => 'date',
            'auto_renew' => 'boolean',
            'beneficiary_notes' => 'array',
            'tags' => 'array',
            'reminder_days' => 'array',
        ];
    }

    protected function maskedAccount(): Attribute
    {
        return Attribute::get(function (): string {
            $value = (string) $this->account_identifier;
            if (str_contains($value, '@')) {
                [$name, $domain] = explode('@', $value, 2);

                return mb_substr($name, 0, min(2, mb_strlen($name))).'***@'.$domain;
            }

            return mb_strlen($value) > 4 ? '••••'.mb_substr($value, -4) : '••••';
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('subscription')->logOnlyDirty()->logFillable();
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function renewalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewal_owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function paymentInstrument(): BelongsTo
    {
        return $this->belongsTo(PaymentInstrument::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SubscriptionVersion::class);
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(SubscriptionLifecycleEvent::class);
    }

    public function renewalDecisions(): HasMany
    {
        return $this->hasMany(SubscriptionRenewalDecision::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(SubscriptionReminder::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(SubscriptionEvidence::class);
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(FinancialEntry::class);
    }

    public function beneficiaries(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
