<?php

namespace App\Jobs;

use App\Models\IntegrationEvent;
use App\Services\LarkApprovalIngestor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;
use UnexpectedValueException;

class ProcessLarkApprovalEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 180];

    public function __construct(public int $eventId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('lark-event-'.$this->eventId))->expireAfter(180)];
    }

    public function handle(LarkApprovalIngestor $ingestor): void
    {
        $event = IntegrationEvent::findOrFail($this->eventId);
        if (in_array($event->status, ['processed', 'quarantined'], true)) {
            return;
        }

        $approvalCode = (string) data_get($event->payload, 'event.approval_code');
        if ($approvalCode !== '' && ! hash_equals((string) config('services.lark.approval_code'), $approvalCode)) {
            $event->update(['status' => 'quarantined', 'error' => 'Unsupported Lark approval definition.', 'processed_at' => now()]);

            return;
        }

        $instanceCode = (string) data_get($event->payload, 'event.instance_code');
        if ($instanceCode === '') {
            $event->update(['status' => 'quarantined', 'error' => 'Missing Lark instance code.', 'processed_at' => now()]);

            return;
        }

        try {
            $ingestor->ingest($instanceCode);
            $event->update(['status' => 'processed', 'error' => null, 'processed_at' => now()]);
        } catch (UnexpectedValueException $exception) {
            $event->update(['status' => 'quarantined', 'error' => $exception->getMessage(), 'processed_at' => now()]);
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
