<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['code', 'parent_id', 'requester_id', 'assigned_to', 'type', 'title', 'description', 'status', 'priority', 'current_stage', 'details', 'estimated_budget', 'currency', 'requested_due_date', 'submitted_at', 'approved_at', 'started_at', 'completed_at', 'cancelled_at'];

    protected $attributes = ['status' => 'submitted', 'priority' => 'normal', 'currency' => 'USD'];

    protected function casts(): array
    {
        return ['type' => ServiceRequestType::class, 'status' => ServiceRequestStatus::class, 'details' => 'array', 'estimated_budget' => 'decimal:2', 'requested_due_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('service_request')->logOnlyDirty()->logFillable();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ServiceRequestApproval::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ServiceRequestUpdate::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(ServiceDelivery::class);
    }

    public function aiCredential(): HasOne
    {
        return $this->hasOne(AiAccessCredential::class);
    }

    public function latestApprovalRound(): int
    {
        return (int) ($this->approvals()->max('round') ?? 1);
    }
}
