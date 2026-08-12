<?php

namespace App\Jobs;

use App\Models\DeployedApplication;
use App\Models\ExternalConnection;
use App\Models\VpsNode;
use App\Services\CoolifyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncCoolifyInventory implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public array $backoff = [10, 60, 180];

    public function handle(CoolifyService $coolify): void
    {
        foreach ($coolify->applications() as $resource) {
            if (! is_array($resource) || blank($resource['uuid'] ?? null)) {
                continue;
            }

            $repository = $this->repository($resource['git_repository'] ?? null)
                ?? 'coolify/'.Str::slug(($resource['name'] ?? 'application').'-'.$resource['uuid']);
            $application = DeployedApplication::where(fn ($query) => $query
                ->where('coolify_uuid', $resource['uuid'])
                ->orWhere('repo_full_name', $repository))
                ->first() ?? new DeployedApplication;
            $serverUuid = data_get($resource, 'server.uuid') ?? data_get($resource, 'destination.server.uuid') ?? data_get($resource, 'destination.server_uuid');

            $application->fill([
                'repo_full_name' => $application->exists ? $application->repo_full_name : $repository,
                'display_name' => $resource['name'] ?? $repository,
                'domain' => $this->domain($resource['fqdn'] ?? null) ?? $application->domain,
                'coolify_uuid' => $resource['uuid'],
                'environment' => ($resource['git_branch'] ?? null) === 'main' ? 'main' : 'virtuenet',
                'runtime_status' => $resource['status'] ?? 'unknown',
                'vps_node_id' => $serverUuid ? VpsNode::where('coolify_server_uuid', $serverUuid)->value('id') : $application->vps_node_id,
                'deployed_at' => $application->deployed_at ?? ($resource['created_at'] ?? now()),
                'sync_enabled' => $application->exists ? $application->sync_enabled : false,
                'backup_enabled' => $application->exists ? $application->backup_enabled : true,
            ])->save();
        }

        ExternalConnection::where('provider', 'coolify')->update([
            'health_status' => 'healthy',
            'last_error' => null,
            'last_checked_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        ExternalConnection::where('provider', 'coolify')->update([
            'health_status' => 'unhealthy',
            'last_error' => $exception?->getMessage(),
            'last_checked_at' => now(),
        ]);
        Log::error('Coolify inventory sync failed.', ['exception' => $exception?->getMessage()]);
    }

    private function repository(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }
        if (preg_match('~github\.com[/:]([^/\s]+/[^/\s]+?)(?:\.git)?$~i', trim($value), $matches)) {
            return preg_replace('/\.git$/i', '', $matches[1]);
        }

        return preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $value) ? $value : null;
    }

    private function domain(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }
        $first = trim(explode(',', $value)[0]);

        return parse_url(Str::startsWith($first, ['http://', 'https://']) ? $first : 'https://'.$first, PHP_URL_HOST) ?: null;
    }
}
