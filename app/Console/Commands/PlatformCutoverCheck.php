<?php

namespace App\Console\Commands;

use App\Models\ExternalConnection;
use App\Services\LarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class PlatformCutoverCheck extends Command
{
    protected $signature = 'platform:cutover-check {--json : Emit machine-readable output}';

    protected $description = 'Fail closed when a shadow or production deployment is not ready for cutover';

    public function handle(LarkService $lark): int
    {
        $checks = [
            'production_environment' => app()->environment('production'),
            'debug_disabled' => ! config('app.debug'),
            'app_key_present' => filled(config('app.key')),
            'https_url' => str_starts_with((string) config('app.url'), 'https://'),
            'postgresql' => $this->postgresReady(),
            'database' => $this->databaseReady(),
            'migrations' => $this->migrationsCurrent(),
            'redis_queue' => config('queue.default') === 'redis' && $this->redisReady(),
            'storage_writable' => is_writable(storage_path()) && is_writable(storage_path('app')),
            'lark_contract' => ! config('services.lark.approval_enabled') || $this->larkReady($lark),
            'integrations' => $this->integrationsReady(),
        ];
        $ready = ! in_array(false, $checks, true);

        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $ready, 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result'], collect($checks)->map(fn (bool $passed, string $name) => [str($name)->replace('_', ' ')->title(), $passed ? 'PASS' : 'FAIL']));
            $this->{$ready ? 'info' : 'error'}($ready ? 'Platform is ready for controlled cutover.' : 'Cutover blocked. Resolve every failed check first.');
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function postgresReady(): bool
    {
        try {
            return DB::getDriverName() === 'pgsql';
        } catch (Throwable) {
            return false;
        }
    }

    private function migrationsCurrent(): bool
    {
        try {
            $files = collect(glob(database_path('migrations/*.php')))->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME));
            $ran = collect(DB::table('migrations')->pluck('migration'));

            return $files->diff($ran)->isEmpty();
        } catch (Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        try {
            $response = Redis::connection()->ping();

            return $response === true || strtoupper(ltrim((string) $response, '+')) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    private function integrationsReady(): bool
    {
        try {
            return ExternalConnection::where('enabled', true)->get()->every(fn (ExternalConnection $connection) => $connection->health_status === 'healthy'
                && $connection->last_checked_at?->gte(now()->subMinutes(15)));
        } catch (Throwable) {
            return false;
        }
    }

    private function larkReady(LarkService $lark): bool
    {
        try {
            return $lark->approvalContractIsSafe();
        } catch (Throwable) {
            return false;
        }
    }
}
