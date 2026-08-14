<?php

namespace App\Jobs;

use App\Models\NotificationBlast;
use App\Models\SystemNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNotificationBlastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public int $blastId;
    public array $userIds;

    /**
     * Create a new job instance.
     */
    public function __construct(int $blastId, array $userIds)
    {
        $this->blastId = $blastId;
        $this->userIds = $userIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $blast = NotificationBlast::find($this->blastId);

        if (! $blast) {
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($this->userIds as $userId) {
            try {
                SystemNotification::send(
                    $userId,
                    $blast->title,
                    $blast->message,
                    $blast->type,
                    'mdi-bell-outline',
                    $blast->url,
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Failed to send blast #{$blast->id} to user #{$userId}: {$e->getMessage()}");
            }
        }

        $blast->update([
            'status'       => 'sent',
            'sent_count'   => $sent,
            'failed_count' => $failed,
        ]);
    }
}
