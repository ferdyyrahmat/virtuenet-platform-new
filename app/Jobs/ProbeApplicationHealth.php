<?php

namespace App\Jobs;

use App\Models\DeployedApplication;
use App\Models\ServiceHealthCheck;
use App\Models\ServiceUptimeIncident;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProbeApplicationHealth implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    public int $uniqueFor = 60;

    public array $backoff = [10, 30];

    public function __construct(public string $repository) {}

    public function uniqueId(): string
    {
        return $this->repository;
    }

    public function handle(): void
    {
        $application = DeployedApplication::find($this->repository);
        if (! $application || blank($application->domain)) {
            $application && $this->record($application, 'no_domain');

            return;
        }
        $addresses = gethostbynamel($application->domain) ?: [];
        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            $this->record($application, 'invalid_domain');

            return;
        }

        $started = hrtime(true);
        try {
            $response = Http::withOptions(['curl' => [CURLOPT_RESOLVE => [$application->domain.':443:'.$addresses[0]]]])
                ->connectTimeout(3)
                ->timeout(8)
                ->get('https://'.$application->domain.'/'.ltrim($application->health_path ?: '/health', '/'));
            $status = $response->status();
            $this->record($application, $status < 400 ? 'online' : ($status < 500 ? 'degraded' : 'offline'), $status, (int) round((hrtime(true) - $started) / 1_000_000));
        } catch (Throwable) {
            $this->record($application, 'offline', null, (int) round((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Application health probe failed.', ['repository' => $this->repository, 'exception' => $exception?->getMessage()]);
    }

    private function record(DeployedApplication $application, string $status, ?int $httpStatus = null, ?int $responseTime = null): void
    {
        $notify = false;
        DB::transaction(function () use ($application, $status, $httpStatus, $responseTime, &$notify): void {
            $application = DeployedApplication::query()->lockForUpdate()->findOrFail($application->getKey());
            $now = now();
            $values = ['health_status' => $status, 'http_status' => $httpStatus, 'response_time_ms' => $responseTime, 'last_checked_at' => $now];

            ServiceHealthCheck::create(['repo_full_name' => $application->repo_full_name, 'status' => $status, 'http_status' => $httpStatus, 'response_time_ms' => $responseTime, 'checked_at' => $now]);

            if ($status === 'offline') {
                $offlineSince = $application->offline_since ?? $now;
                $values['offline_since'] = $offlineSince;
                $incident = ServiceUptimeIncident::firstOrCreate(
                    ['repo_full_name' => $application->repo_full_name, 'ended_at' => null],
                    ['started_at' => $offlineSince, 'last_status' => $status]
                );
                if (! $incident->notified_at && $offlineSince->lte($now->copy()->subMinutes(15))) {
                    $incident->update(['notified_at' => $now]);
                    $notify = true;
                }
            } elseif ($application->offline_since) {
                ServiceUptimeIncident::where('repo_full_name', $application->repo_full_name)->whereNull('ended_at')->update([
                    'ended_at' => $now,
                    'duration_seconds' => $application->offline_since->diffInSeconds($now),
                    'last_status' => $status,
                    'updated_at' => $now,
                ]);
                $values['offline_since'] = null;
                $values['last_recovered_at'] = $now;
            }

            $application->update($values);
        });

        if ($notify) {
            $this->notifyOwners($application);
        }
    }

    private function notifyOwners(DeployedApplication $application): void
    {
        $userIds = User::permission('manage integrations')->pluck('id');
        if ($application->department_id) {
            $userIds = $userIds->merge(User::permission('manage service requests')
                ->whereHas('departments', fn ($query) => $query->whereKey($application->department_id))
                ->pluck('id'));
        }
        if ($application->owner_id) {
            $userIds->push($application->owner_id);
        }

        $userIds->unique()->each(fn (int $userId) => SystemNotification::send(
            $userId,
            'Service health alert',
            ($application->display_name ?: $application->repo_full_name).' has been offline for at least 15 minutes.',
            'danger',
            'mdi-server-off',
            route('v1.services.index')
        ));
    }
}
