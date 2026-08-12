<?php

namespace App\Jobs;

use App\Models\ExternalConnection;
use App\Models\GithubTask;
use App\Models\IntegrationEvent;
use App\Services\GithubLarkSyncService;
use App\Services\GithubTaskService;
use App\Services\LarkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncGithubTasks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('github-lark-sync'))->expireAfter(300)];
    }

    public function handle(GithubTaskService $github, GithubLarkSyncService $sync, LarkService $lark): void
    {
        if ($sync->configured()) {
            $sync->syncAll();
            IntegrationEvent::where('provider', 'github')->where('status', 'queued')->update(['status' => 'processed', 'processed_at' => now()]);

            return;
        }

        if (! $github->configured()) {
            return;
        }

        $repository = data_get(ExternalConnection::where('provider', 'github')->first()?->settings, 'repository');

        foreach ($github->issues() as $issue) {
            $task = GithubTask::updateOrCreate(
                ['repository' => $repository, 'issue_number' => $issue['number']],
                [
                    'github_node_id' => $issue['node_id'] ?? null,
                    'title' => $issue['title'],
                    'body' => $issue['body'] ?? null,
                    'state' => $issue['state'],
                    'labels' => collect($issue['labels'] ?? [])->pluck('name')->all(),
                    'assignee' => data_get($issue, 'assignee.login'),
                    'mandays' => preg_match('/mandays\s*:\s*([0-9]+(?:\.[0-9]+)?)/i', (string) ($issue['body'] ?? ''), $matches) ? (float) $matches[1] : null,
                    'github_url' => $issue['html_url'],
                    'remote_updated_at' => $issue['updated_at'] ?? null,
                    'last_synced_at' => now(),
                ]
            );

            if (! $task->lark_task_id) {
                try {
                    $description = str(trim(($task->body ?? '')."\n\nGitHub: {$task->github_url}"))->limit(3000)->toString();
                    $created = $lark->createTask(str($task->title)->limit(200)->toString(), $description);
                    $task->update([
                        'lark_task_id' => $created['guid'] ?? $created['id'] ?? null,
                        'lark_task_url' => $created['url'] ?? null,
                        'sync_status' => 'synced',
                        'sync_error' => null,
                    ]);
                } catch (\Throwable $exception) {
                    $task->update(['sync_status' => 'failed', 'sync_error' => $exception->getMessage()]);
                }
            } else {
                $task->update(['sync_status' => 'synced', 'sync_error' => null]);
            }
        }

        IntegrationEvent::where('provider', 'github')
            ->where('status', 'queued')
            ->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
