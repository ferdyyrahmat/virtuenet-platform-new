<?php

namespace App\Jobs;

use App\Enums\RequestFulfilmentStatus;
use App\Enums\ServiceRequestType;
use App\Models\AiAccessCredential;
use App\Models\ServiceRequest;
use App\Models\SystemNotification;
use App\Services\LiteLlmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;

class ProvisionAiCredential implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public ServiceRequest $request) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('ai-provision-'.$this->request->id))->expireAfter(180)];
    }

    public function handle(LiteLlmService $liteLlm): void
    {
        $request = $this->request->fresh();
        if ($request->aiCredential || ! $liteLlm->configured()) {
            if (! $liteLlm->configured()) {
                throw new RuntimeException('LiteLLM gateway is not configured.');
            }

            return;
        }

        $details = $request->details;
        $models = $request->type === ServiceRequestType::AiToken
            ? data_get($details, 'models', [])
            : data_get($details, 'ai_models', []);
        $maxBudget = (float) ($request->type === ServiceRequestType::AiToken
            ? data_get($details, 'max_budget')
            : data_get($details, 'ai_max_budget'));
        $externalUserId = 'virtuenet-user-'.$request->requester_id;
        $alias = strtolower($request->code);

        $response = $liteLlm->generateKey(array_filter([
            'key_alias' => $alias,
            'user_id' => $externalUserId,
            'models' => $models,
            'max_budget' => $maxBudget,
            'budget_duration' => data_get($details, 'budget_duration', 'monthly'),
            'rpm_limit' => data_get($details, 'rpm_limit') ? (int) data_get($details, 'rpm_limit') : null,
            'tpm_limit' => data_get($details, 'tpm_limit') ? (int) data_get($details, 'tpm_limit') : null,
            'metadata' => ['service_request_code' => $request->code, 'local_user_id' => $request->requester_id],
        ], fn ($value): bool => $value !== null && $value !== []));

        $key = $response['key'] ?? $response['token'] ?? null;
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('LiteLLM did not return a virtual key.');
        }

        AiAccessCredential::create([
            'service_request_id' => $request->id,
            'user_id' => $request->requester_id,
            'external_user_id' => $externalUserId,
            'key_alias' => $alias,
            'virtual_key' => $key,
            'key_hash' => hash('sha256', $key),
            'key_preview' => substr($key, 0, 7).'...'.substr($key, -4),
            'gateway_key_id' => $response['key_id'] ?? $response['key_name'] ?? null,
            'reveal_expires_at' => now()->addDay(),
            'models' => $models,
            'max_budget' => $maxBudget,
            'budget_duration' => data_get($details, 'budget_duration', 'monthly'),
            'rpm_limit' => data_get($details, 'rpm_limit'),
            'tpm_limit' => data_get($details, 'tpm_limit'),
            'metadata' => ['litellm' => collect($response)->except(['key', 'token'])->all()],
        ]);

        $request->update(['fulfilment_status' => RequestFulfilmentStatus::Active]);
        $request->updates()->create(['type' => 'provisioned', 'message' => 'AI access token provisioned and ready to use.']);
        SystemNotification::send($request->requester_id, 'AI token ready', $request->code.' is ready to use.', 'success', 'mdi-key-variant', route('v1.requests.show', $request));
    }

    public function failed(?\Throwable $exception): void
    {
        $this->request->update(['fulfilment_status' => RequestFulfilmentStatus::Failed]);
        $this->request->updates()->create(['type' => 'integration_error', 'message' => 'AI token provisioning needs operator attention.']);
        SystemNotification::send($this->request->requester_id, 'AI token pending', 'Your request is approved, but token delivery is still being processed.', 'warning', 'mdi-alert-circle-outline', route('v1.requests.show', $this->request));
    }
}
