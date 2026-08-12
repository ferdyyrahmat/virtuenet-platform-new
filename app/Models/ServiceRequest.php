<?php

namespace App\Models;

use App\Enums\RequestApprovalStatus;
use App\Enums\RequestFulfilmentStatus;
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

    protected $fillable = ['legacy_uuid', 'code', 'parent_id', 'template_id', 'requester_id', 'department_id', 'assigned_to', 'type', 'schema_version', 'title', 'description', 'status', 'approval_status', 'fulfilment_status', 'priority', 'current_stage', 'details', 'estimated_budget', 'currency', 'requested_due_date', 'submitted_at', 'approved_at', 'started_at', 'completed_at', 'cancelled_at', 'source', 'source_record_id', 'idempotency_key', 'approval_source', 'lark_approval_code', 'lark_instance_code', 'lark_status', 'lark_approval_url', 'lark_contract_hash', 'lark_form_snapshot', 'approval_sync_status', 'approval_synced_at', 'source_created_at', 'source_updated_at'];

    protected $attributes = ['status' => 'submitted', 'priority' => 'normal', 'currency' => 'USD'];

    protected function casts(): array
    {
        return ['type' => ServiceRequestType::class, 'status' => ServiceRequestStatus::class, 'approval_status' => RequestApprovalStatus::class, 'fulfilment_status' => RequestFulfilmentStatus::class, 'details' => 'array', 'lark_form_snapshot' => 'encrypted:array', 'estimated_budget' => 'decimal:2', 'requested_due_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'approval_synced_at' => 'datetime', 'source_created_at' => 'datetime', 'source_updated_at' => 'datetime'];
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RequestTemplate::class);
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

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(FinancialEntry::class);
    }

    public function aiCredential(): HasOne
    {
        return $this->hasOne(AiAccessCredential::class);
    }

    public function supportTicket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class);
    }

    public function latestApprovalRound(): int
    {
        return (int) ($this->approvals()->max('round') ?? 1);
    }
}
