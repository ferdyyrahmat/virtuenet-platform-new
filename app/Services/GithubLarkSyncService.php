<?php

namespace App\Services;

use App\Models\ExternalConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubLarkSyncService
{
    public function configured(): bool
    {
        $connection = $this->connection();

        return $connection?->enabled && filled($connection->base_url);
    }

    public function health(): array
    {
        return $this->client()->get('/health')->throw()->json();
    }

    public function syncAll(): array
    {
        return $this->client(180)->post('/api/sync')->throw()->json();
    }

    private function client(int $timeout = 15): PendingRequest
    {
        $connection = $this->connection();
        if (! $connection?->enabled || blank($connection->base_url)) {
            throw new RuntimeException('GitHub–Lark Sync endpoint is not configured or disabled.');
        }

        $request = Http::baseUrl(rtrim($connection->base_url, '/'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout($timeout)
            ->retry(2, 250, throw: false);

        if ($token = data_get($connection->credentials, 'api_key')) {
            $request->withToken($token);
        }

        return $request;
    }

    private function connection(): ?ExternalConnection
    {
        return ExternalConnection::where('provider', 'github_lark_sync')->first();
    }
}
