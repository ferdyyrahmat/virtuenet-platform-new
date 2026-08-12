<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLarkApprovalEvent;
use App\Models\IntegrationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LarkWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->verifySignature($request);
        $payload = $this->payload($request);
        $this->verifyIdentity($payload);

        if (filled(data_get($payload, 'challenge'))) {
            return response()->json(['challenge' => data_get($payload, 'challenge')]);
        }

        $eventId = (string) (data_get($payload, 'header.event_id') ?: data_get($payload, 'uuid'));
        abort_if($eventId === '', 422, 'Missing Lark event identifier.');

        $event = IntegrationEvent::firstOrCreate(
            ['provider' => 'lark', 'external_id' => $eventId],
            ['event_type' => (string) (data_get($payload, 'header.event_type') ?: data_get($payload, 'event.type')), 'payload' => $payload]
        );
        if (! $event->wasRecentlyCreated && in_array($event->status, ['processed', 'quarantined'], true)) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }

        ProcessLarkApprovalEvent::dispatch($event->id);

        return response()->json(['success' => true, 'duplicate' => false]);
    }

    private function verifyIdentity(array $payload): void
    {
        $expected = (string) config('services.lark.verification_token');
        abort_if($expected === '', 503, 'Lark webhook is not configured.');
        $actual = (string) (data_get($payload, 'header.token') ?: data_get($payload, 'token'));
        abort_unless($actual !== '' && hash_equals($expected, $actual), 401);

        $appId = (string) (data_get($payload, 'header.app_id') ?: data_get($payload, 'event.app_id'));
        abort_if(! data_get($payload, 'challenge') && ($appId === '' || ! hash_equals((string) config('services.lark.app_id'), $appId)), 401);
    }

    private function verifySignature(Request $request): void
    {
        $encryptKey = (string) config('services.lark.encrypt_key');
        if ($encryptKey === '') {
            return;
        }

        $timestamp = (string) $request->header('X-Lark-Request-Timestamp');
        $nonce = (string) $request->header('X-Lark-Request-Nonce');
        $signature = (string) $request->header('X-Lark-Signature');
        abort_if($timestamp === '' || $nonce === '' || $signature === '', 401);
        abort_if(abs(now()->timestamp - (int) $timestamp) > config('services.lark.webhook_clock_skew'), 401);
        $expectedSignature = hash('sha256', $timestamp.$nonce.$encryptKey.$request->getContent());
        abort_unless(hash_equals($expectedSignature, $signature), 401);
    }

    private function payload(Request $request): array
    {
        $payload = $request->all();
        if (! isset($payload['encrypt'])) {
            return $payload;
        }

        $decoded = base64_decode((string) $payload['encrypt'], true);
        abort_if($decoded === false || strlen($decoded) <= 16, 400, 'Invalid encrypted Lark payload.');
        $plain = openssl_decrypt(
            substr($decoded, 16),
            'AES-256-CBC',
            hash('sha256', (string) config('services.lark.encrypt_key'), true),
            OPENSSL_RAW_DATA,
            substr($decoded, 0, 16)
        );
        $payload = is_string($plain) ? json_decode($plain, true) : null;
        abort_unless(is_array($payload), 400, 'Unable to decrypt Lark payload.');

        return $payload;
    }
}
