<?php

namespace App\Jobs;

use App\Models\DeployedApplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;

class ProbeApplicationHealth implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $repository) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('application-health-'.sha1($this->repository)))->expireAfter(30)];
    }

    public function handle(): void
    {
        $application = DeployedApplication::find($this->repository);
        if (! $application || blank($application->domain)) {
            $application?->update(['health_status' => 'no_domain', 'http_status' => null, 'response_time_ms' => null, 'last_checked_at' => now()]);

            return;
        }
        $addresses = gethostbynamel($application->domain) ?: [];
        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            $application->update(['health_status' => 'invalid_domain', 'http_status' => null, 'response_time_ms' => null, 'last_checked_at' => now()]);

            return;
        }

        $started = hrtime(true);
        try {
            $response = Http::connectTimeout(3)->timeout(8)->get('https://'.$application->domain);
            $status = $response->status();
            $application->update([
                'health_status' => $status < 400 ? 'online' : ($status < 500 ? 'degraded' : 'offline'),
                'http_status' => $status,
                'response_time_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'last_checked_at' => now(),
            ]);
        } catch (\Throwable) {
            $application->update([
                'health_status' => 'offline',
                'http_status' => null,
                'response_time_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'last_checked_at' => now(),
            ]);
        }
    }
}
