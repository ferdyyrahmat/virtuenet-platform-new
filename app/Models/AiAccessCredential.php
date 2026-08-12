<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAccessCredential extends Model
{
    protected $fillable = ['legacy_uuid', 'service_request_id', 'user_id', 'external_user_id', 'key_alias', 'virtual_key', 'key_hash', 'key_preview', 'gateway_key_id', 'models', 'max_budget', 'current_spend', 'budget_duration', 'rpm_limit', 'tpm_limit', 'status', 'expires_at', 'last_synced_at', 'reveal_expires_at', 'revealed_at', 'revoked_at', 'metadata'];

    protected $hidden = ['virtual_key'];

    protected function casts(): array
    {
        return ['virtual_key' => 'encrypted', 'models' => 'array', 'metadata' => 'array', 'max_budget' => 'decimal:4', 'current_spend' => 'decimal:4', 'expires_at' => 'datetime', 'last_synced_at' => 'datetime', 'reveal_expires_at' => 'datetime', 'revealed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
