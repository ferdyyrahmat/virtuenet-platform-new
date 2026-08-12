<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\LarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => $this->database()];

        if (in_array('redis', [config('cache.default'), config('queue.default'), config('session.driver')], true)) {
            $checks['redis'] = $this->redis();
        }
        if (config('services.lark.approval_enabled')) {
            $checks['lark_contract'] = app(LarkService::class)->approvalContractIsSafe();
        }

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    private function database(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function redis(): bool
    {
        try {
            $response = Redis::connection()->ping();

            return $response === true || strtoupper(ltrim((string) $response, '+')) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }
}
