<?php

namespace App\Services;

use App\Models\ExternalConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LarkService
{
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return rtrim(config('services.lark.accounts_url'), '/').'/open-apis/authen/v1/authorize?'.http_build_query([
            'app_id' => $this->appId(),
            'redirect_uri' => config('services.lark.redirect_uri'),
            'state' => $state,
            'scope' => config('services.lark.scope'),
        ]);
    }

    public function userFromCode(string $code): array
    {
        $this->ensureConfigured();

        $token = $this->requestToken([
            'grant_type' => 'authorization_code',
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'code' => $code,
            'redirect_uri' => config('services.lark.redirect_uri'),
        ]);

        $response = $this->client()
            ->withToken($token['access_token'])
            ->get('/open-apis/authen/v1/user_info')
            ->throw()
            ->json();

        $this->throwIfLarkFailed($response);

        return $response['data'];
    }

    public function tenantAccessToken(): string
    {
        $this->ensureConfigured();

        if ($token = Cache::get('lark.tenant_access_token')) {
            return $token;
        }

        $response = $this->client()
            ->post('/open-apis/auth/v3/tenant_access_token/internal', [
                'app_id' => $this->appId(),
                'app_secret' => $this->appSecret(),
            ])
            ->throw()
            ->json();

        $this->throwIfLarkFailed($response);

        $token = $response['tenant_access_token'];
        Cache::put('lark.tenant_access_token', $token, now()->addSeconds(max(60, ($response['expire'] ?? 7200) - 60)));

        return $token;
    }

    public function sendMessage(string $receiveId, string $text, string $receiveIdType = 'open_id'): array
    {
        $response = $this->client()
            ->withToken($this->tenantAccessToken())
            ->post('/open-apis/im/v1/messages?'.http_build_query(['receive_id_type' => $receiveIdType]), [
                'receive_id' => $receiveId,
                'msg_type' => 'text',
                'content' => json_encode(['text' => $text], JSON_THROW_ON_ERROR),
            ])->throw()->json();
        $this->throwIfLarkFailed($response);

        return $response['data'] ?? [];
    }

    public function createTask(string $summary, string $description): array
    {
        $response = $this->client()
            ->withToken($this->tenantAccessToken())
            ->post('/open-apis/task/v2/tasks', compact('summary', 'description'))
            ->throw()->json();
        $this->throwIfLarkFailed($response);

        return $response['data']['task'] ?? $response['data'] ?? [];
    }

    public function createApprovalInstance(array $payload): array
    {
        $response = $this->client()
            ->withToken($this->tenantAccessToken())
            ->post('/open-apis/approval/v4/instances', $payload)
            ->throw()->json();
        $this->throwIfLarkFailed($response);

        return $response['data'] ?? [];
    }

    public function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->connection()?->base_url ?: config('services.lark.open_api_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(3);
    }

    private function requestToken(array $payload): array
    {
        $response = $this->client()
            ->post('/open-apis/authen/v2/oauth/token', $payload)
            ->throw()
            ->json();

        $this->throwIfLarkFailed($response);

        return $response;
    }

    private function ensureConfigured(): void
    {
        if (blank($this->appId()) || blank($this->appSecret()) || blank(config('services.lark.redirect_uri'))) {
            throw new RuntimeException('Lark SSO is not configured.');
        }
    }

    private function appId(): ?string
    {
        return data_get($this->connection()?->credentials, 'app_id') ?: config('services.lark.app_id');
    }

    private function appSecret(): ?string
    {
        return data_get($this->connection()?->credentials, 'app_secret') ?: config('services.lark.app_secret');
    }

    private function connection(): ?ExternalConnection
    {
        return Schema::hasTable('external_connections')
            ? ExternalConnection::where('provider', 'lark')->where('enabled', true)->first()
            : null;
    }

    private function throwIfLarkFailed(array $response): void
    {
        if (($response['code'] ?? 0) !== 0) {
            throw new RuntimeException($response['msg'] ?? $response['error_description'] ?? 'Lark request failed.');
        }
    }
}
