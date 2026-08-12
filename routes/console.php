<?php

use App\Jobs\SyncGithubTasks;
use App\Services\GithubTaskService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('activitylog:clean')->daily();
Schedule::job(new SyncGithubTasks)
    ->everyFiveMinutes()
    ->when(fn (): bool => app(GithubTaskService::class)->configured())
    ->withoutOverlapping()
    ->onOneServer();
