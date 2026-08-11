<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LarkService
{
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        return rtrim(config('services.lark.accounts_url'), '/') . '/open-apis/authen/v1/authorize?' . http_build_query([
            'app_id' => config('services.lark.app_id'),
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
            'client_id' => config('services.lark.app_id'),
            'client_secret' => config('services.lark.app_secret'),
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
                'app_id' => config('services.lark.app_id'),
                'app_secret' => config('services.lark.app_secret'),
            ])
            ->throw()
            ->json();

        $this->throwIfLarkFailed($response);

        $token = $response['tenant_access_token'];
        Cache::put('lark.tenant_access_token', $token, now()->addSeconds(max(60, ($response['expire'] ?? 7200) - 60)));

        return $token;
    }

    public function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.lark.open_api_url'), '/'))
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
        if (blank(config('services.lark.app_id')) || blank(config('services.lark.app_secret')) || blank(config('services.lark.redirect_uri'))) {
            throw new RuntimeException('Lark SSO is not configured.');
        }
    }

    private function throwIfLarkFailed(array $response): void
    {
        if (($response['code'] ?? 0) !== 0) {
            throw new RuntimeException($response['msg'] ?? $response['error_description'] ?? 'Lark request failed.');
        }
    }
}
