<?php

namespace App\Services;

use App\Models\AiAccessCredential;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiCredentialService
{
    public function __construct(private LiteLlmService $liteLlm) {}

    public function changeStatus(AiAccessCredential $credential, string $status): AiAccessCredential
    {
        match ($status) {
            'active' => $this->liteLlm->unblockKey($credential->virtual_key),
            'paused' => $this->liteLlm->blockKey($credential->virtual_key),
            'revoked' => $this->liteLlm->deleteKey($credential->virtual_key),
            default => throw new RuntimeException('Unsupported AI credential status.'),
        };

        $credential->update([
            'status' => $status,
            'revoked_at' => $status === 'revoked' ? now() : null,
            'last_synced_at' => now(),
        ]);

        return $credential->refresh();
    }

    public function updateQuota(AiAccessCredential $credential, array $changes): AiAccessCredential
    {
        $remote = array_filter([
            'models' => $changes['models'] ?? null,
            'max_budget' => $changes['max_budget'] ?? null,
            'rpm_limit' => $changes['rpm_limit'] ?? null,
            'tpm_limit' => $changes['tpm_limit'] ?? null,
        ], fn ($value): bool => $value !== null);

        $this->liteLlm->updateKey($credential->virtual_key, $remote);
        $credential->update([...$remote, 'last_synced_at' => now()]);

        return $credential->refresh();
    }

    public function rotate(AiAccessCredential $credential): AiAccessCredential
    {
        $response = $this->liteLlm->generateKey(array_filter([
            'key_alias' => $credential->key_alias,
            'user_id' => $credential->external_user_id,
            'models' => $credential->models,
            'max_budget' => $credential->max_budget,
            'budget_duration' => $credential->budget_duration,
            'rpm_limit' => $credential->rpm_limit,
            'tpm_limit' => $credential->tpm_limit,
        ], fn ($value): bool => $value !== null && $value !== []));

        $key = $response['key'] ?? $response['token'] ?? null;
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('LiteLLM did not return a replacement virtual key.');
        }

        try {
            $this->liteLlm->deleteKey($credential->virtual_key);
        } catch (\Throwable $exception) {
            $this->liteLlm->deleteKey($key);
            throw $exception;
        }

        return DB::transaction(function () use ($credential, $response, $key): AiAccessCredential {
            $credential->update([
                'virtual_key' => $key,
                'key_hash' => hash('sha256', $key),
                'key_preview' => substr($key, 0, 7).'...'.substr($key, -4),
                'gateway_key_id' => $response['key_id'] ?? $response['key_name'] ?? null,
                'status' => 'active',
                'reveal_expires_at' => now()->addDay(),
                'revealed_at' => null,
                'revoked_at' => null,
                'last_synced_at' => now(),
            ]);

            return $credential->refresh();
        });
    }
}
