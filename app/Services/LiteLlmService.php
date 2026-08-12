<?php

namespace App\Services;

use App\Models\ExternalConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LiteLlmService
{
    public function health(): array
    {
        return $this->client()->get('/health/readiness')->throw()->json();
    }

    public function generateKey(array $payload): array
    {
        return $this->client()->post('/key/generate', $payload)->throw()->json();
    }

    public function userInfo(string $userId): array
    {
        return $this->client()->get('/user/info', ['user_id' => $userId])->throw()->json();
    }

    public function usage(?string $userId, string $startDate, string $endDate): array
    {
        $parameters = array_filter([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $daily = $this->client()->get('/user/daily/activity', $parameters)->throw()->json();

        $logs = $this->client()->get('/spend/logs', [...$parameters, 'summarize' => 'false'])->throw()->json();

        $summary = is_array($daily['metadata'] ?? null) ? $daily['metadata'] : [];
        $activity = $daily['results'] ?? $daily['data'] ?? [];
        $spendLogs = $logs['data'] ?? $logs;
        $activity = is_array($activity) ? $activity : [];
        $spendLogs = is_array($spendLogs) ? $spendLogs : [];

        return [
            'summary' => $summary,
            'metrics' => $this->summarize($summary, $spendLogs),
            'daily' => $activity,
            'logs' => $spendLogs,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    public function configured(): bool
    {
        $connection = ExternalConnection::where('provider', 'litellm')->first();

        return $connection?->enabled
            && filled($connection->base_url)
            && filled(data_get($connection->credentials, 'master_key'));
    }

    private function client(bool $authenticated = true): PendingRequest
    {
        $connection = ExternalConnection::where('provider', 'litellm')->first();
        if (! $connection?->enabled || blank($connection->base_url)) {
            throw new RuntimeException('LiteLLM gateway is not configured or disabled.');
        }

        $client = Http::baseUrl(rtrim($connection->base_url, '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(15)
            ->retry(2, 250, throw: false);

        if ($authenticated) {
            $key = data_get($connection->credentials, 'master_key');
            if (blank($key)) {
                throw new RuntimeException('LiteLLM master key is not configured.');
            }
            $client->withToken($key);
        }

        return $client;
    }

    private function summarize(array $summary, array $logs): array
    {
        $logs = collect($logs)->filter(fn ($log): bool => is_array($log));
        $inputTokens = $this->number($summary, ['total_prompt_tokens', 'prompt_tokens', 'input_tokens'])
            ?? $logs->sum(fn (array $log): float => $this->number($log, ['prompt_tokens', 'input_tokens']) ?? 0);
        $outputTokens = $this->number($summary, ['total_completion_tokens', 'completion_tokens', 'output_tokens'])
            ?? $logs->sum(fn (array $log): float => $this->number($log, ['completion_tokens', 'output_tokens']) ?? 0);
        $spend = $this->number($summary, ['total_spend', 'spend'])
            ?? $logs->sum(fn (array $log): float => $this->number($log, ['spend', 'response_cost']) ?? 0);
        $requests = $this->number($summary, ['total_requests', 'api_requests', 'requests']) ?? $logs->count();
        $successful = $logs->filter(function (array $log): bool {
            $statusCode = $this->number($log, ['status_code', 'response_status']);
            $status = strtolower((string) data_get($log, 'status', ''));

            return $statusCode !== null
                ? $statusCode >= 200 && $statusCode < 400
                : blank(data_get($log, 'failure_reason')) && ! in_array($status, ['failed', 'failure', 'error'], true);
        })->count();

        return [
            'spend' => round($spend, 6),
            'requests' => (int) $requests,
            'input_tokens' => (int) $inputTokens,
            'output_tokens' => (int) $outputTokens,
            'total_tokens' => (int) ($inputTokens + $outputTokens),
            'models' => $logs->map(fn (array $log): mixed => data_get($log, 'model') ?? data_get($log, 'model_group'))->filter()->unique()->count(),
            'success_rate' => $logs->isEmpty() ? null : round(($successful / $logs->count()) * 100, 1),
        ];
    }

    private function number(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
