<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PaymentInstrument extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'alias', 'issuer', 'provider', 'last_four', 'custodian_id', 'department_id', 'cost_center',
        'expiry_month', 'expiry_year', 'status', 'created_by', 'updated_by',
    ];

    protected function maskedLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->alias.' · •••• '.$this->last_four);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('payment_instrument')->logOnlyDirty()->logFillable();
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
