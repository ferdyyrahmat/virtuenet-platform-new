<?php

namespace App\Console\Commands;

use App\Services\LiteLlmService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiGatewaySmokeCheck extends Command
{
    protected $signature = 'platform:ai-gateway-smoke
        {--model= : Optional model allowlist for the canary key}
        {--json : Emit machine-readable output}';

    protected $description = 'Verify LiteLLM readiness and virtual-key provisioning/revocation without exposing the generated secret';

    public function handle(LiteLlmService $liteLlm): int
    {
        $alias = 'virtuenet-smoke-'.Str::lower(Str::random(12));
        $key = null;
        $error = null;
        $checks = [
            'configured' => false,
            'health' => false,
            'key_generated' => false,
            'key_revoked' => false,
        ];

        try {
            if (! $liteLlm->configured()) {
                throw new RuntimeException('LiteLLM gateway is not configured.');
            }
            $checks['configured'] = true;

            $liteLlm->health();
            $checks['health'] = true;

            $payload = [
                'key_alias' => $alias,
                'user_id' => 'virtuenet-smoke',
                'max_budget' => 0.01,
                'budget_duration' => '1d',
                'metadata' => [
                    'purpose' => 'virtuenet-platform-issue-46-smoke',
                    'generated_at' => now()->toIso8601String(),
                ],
            ];

            if (filled($this->option('model'))) {
                $payload['models'] = [(string) $this->option('model')];
            }

            $response = $liteLlm->generateKey($payload);
            $key = $response['key'] ?? $response['token'] ?? null;
            if (! is_string($key) || $key === '') {
                throw new RuntimeException('LiteLLM did not return a virtual key for the smoke check.');
            }
            $checks['key_generated'] = true;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        } finally {
            if (is_string($key) && $key !== '') {
                try {
                    $liteLlm->deleteKey($key);
                    $checks['key_revoked'] = true;
                } catch (Throwable $cleanupException) {
                    $error = trim(($error ? $error.' ' : '').'Canary key cleanup failed: '.$cleanupException->getMessage());
                }
            }
        }

        $passed = ! in_array(false, $checks, true) && $error === null;
        $result = [
            'passed' => $passed,
            'alias' => $alias,
            'checks' => $checks,
            'error' => $error,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Check', 'Result'],
                collect($checks)->map(fn (bool $value, string $name): array => [str($name)->replace('_', ' ')->title(), $value ? 'PASS' : 'FAIL'])
            );
            $this->{$passed ? 'info' : 'error'}($passed
                ? 'LiteLLM provisioning smoke check passed; the canary key was revoked.'
                : 'LiteLLM provisioning smoke check failed'.($error ? ': '.$error : '.'));
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
