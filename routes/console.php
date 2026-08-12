<?php

use App\Jobs\ProbeApplicationHealth;
use App\Jobs\ProcessSubscriptionRenewals;
use App\Jobs\SyncCoolifyInventory;
use App\Jobs\SyncGithubTasks;
use App\Models\DeployedApplication;
use App\Models\ServiceHealthCheck;
use App\Services\CoolifyService;
use App\Services\GithubTaskService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('activitylog:clean')->daily();
Schedule::command('platform:sync-lark-approvals')
    ->everyTenMinutes()
    ->when(fn (): bool => (bool) config('services.lark.approval_enabled'))
    ->withoutOverlapping()
    ->onOneServer();
Schedule::job(new SyncGithubTasks)
    ->everyFiveMinutes()
    ->when(fn (): bool => app(GithubTaskService::class)->configured())
    ->withoutOverlapping()
    ->onOneServer();
Schedule::call(fn () => DeployedApplication::query()->pluck('repo_full_name')->each(fn (string $repository) => ProbeApplicationHealth::dispatch($repository)))
    ->name('probe-application-health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
Schedule::job(new SyncCoolifyInventory)
    ->everyTenMinutes()
    ->when(fn (): bool => app(CoolifyService::class)->configured())
    ->withoutOverlapping()
    ->onOneServer();
Schedule::call(fn () => ServiceHealthCheck::where('checked_at', '<', now()->subDays(90))->delete())
    ->name('prune-service-health-checks')
    ->daily()
    ->onOneServer();
Schedule::job(new ProcessSubscriptionRenewals)
    ->name('process-subscription-renewals')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
