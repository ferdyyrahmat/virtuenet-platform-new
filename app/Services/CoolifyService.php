<?php

namespace App\Services;

use App\Models\ExternalConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoolifyService
{
    public function configured(): bool
    {
        $connection = $this->connection();

        return $connection?->enabled && filled($connection->base_url) && filled(data_get($connection->credentials, 'token'));
    }

    public function applications(): array
    {
        $payload = $this->client()->get('/api/v1/applications')->throw()->json();

        return array_is_list($payload) ? $payload : ($payload['data'] ?? []);
    }

    public function health(): array
    {
        $applications = $this->applications();

        return ['healthy' => true, 'applications' => count($applications)];
    }

    private function client(): PendingRequest
    {
        $connection = $this->connection();
        if (! $connection?->enabled || blank($connection->base_url) || blank(data_get($connection->credentials, 'token'))) {
            throw new RuntimeException('Coolify endpoint and read-only API token are not configured or enabled.');
        }

        return Http::baseUrl(rtrim($connection->base_url, '/'))
            ->withToken(data_get($connection->credentials, 'token'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }

    private function connection(): ?ExternalConnection
    {
        return ExternalConnection::where('provider', 'coolify')->first();
    }
}
