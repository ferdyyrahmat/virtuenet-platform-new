<?php

namespace App\Services;

use App\Models\ExternalConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubTaskService
{
    public function configured(): bool
    {
        $connection = ExternalConnection::where('provider', 'github')->first();

        return $connection?->enabled && filled(data_get($connection->settings, 'repository'));
    }

    public function issues(): Collection
    {
        $connection = ExternalConnection::where('provider', 'github')->first();
        $repository = data_get($connection?->settings, 'repository');
        if (! $connection?->enabled || blank($repository)) {
            throw new RuntimeException('GitHub task connection is not configured or disabled.');
        }

        $client = Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->withUserAgent(config('app.name').'-github-sync')
            ->connectTimeout(3)
            ->timeout(15)
            ->retry(2, 250, throw: false);

        if ($token = data_get($connection->credentials, 'token')) {
            $client->withToken($token);
        }

        return collect($client->get("/repos/{$repository}/issues", [
            'state' => 'all',
            'per_page' => 100,
            'sort' => 'updated',
            'direction' => 'desc',
        ])->throw()->json())
            ->reject(fn (array $issue): bool => isset($issue['pull_request']))
            ->values();
    }
}
