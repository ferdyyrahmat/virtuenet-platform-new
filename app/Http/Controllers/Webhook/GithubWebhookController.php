<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGithubTasks;
use App\Models\ExternalConnection;
use App\Models\IntegrationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GithubWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $connection = ExternalConnection::where('provider', 'github')->where('enabled', true)->first();
        $secret = data_get($connection?->credentials, 'webhook_secret');
        abort_if(blank($secret), 503, 'GitHub webhook is not configured.');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 401);

        $delivery = (string) $request->header('X-GitHub-Delivery');
        abort_if($delivery === '', 422, 'Missing delivery identifier.');

        $event = IntegrationEvent::firstOrCreate(
            ['provider' => 'github', 'external_id' => $delivery],
            ['event_type' => (string) $request->header('X-GitHub-Event'), 'payload' => $request->all()]
        );

        if ($event->wasRecentlyCreated) {
            SyncGithubTasks::dispatch();
            $event->update(['status' => 'queued']);
        }

        return response()->json(['success' => true, 'duplicate' => ! $event->wasRecentlyCreated]);
    }
}
