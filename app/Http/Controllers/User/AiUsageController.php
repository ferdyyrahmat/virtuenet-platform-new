<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ExternalConnection;
use App\Services\LiteLlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiUsageController extends Controller
{
    public function index(Request $request): View
    {
        $credentials = $request->user()->aiCredentials()->with(['user', 'request'])
            ->when($request->string('attention')->toString() === 'expiring', fn ($query) => $query->where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(30)]))
            ->latest()->get();

        return view('ai-usage.index', [
            'credentials' => $credentials,
            'dataUrl' => route('v1.ai-usage.data'),
            'scopeLabel' => 'My AI monitoring',
            'gateway' => ExternalConnection::where('provider', 'litellm')->first(),
            'isOrganizationScope' => false,
        ]);
    }

    public function data(Request $request, LiteLlmService $liteLlm): JsonResponse
    {
        $credential = $request->user()->aiCredentials()->where('status', 'active')->latest()->first();
        if (! $credential) {
            return response()->json(['success' => true, 'data' => ['summary' => [], 'metrics' => [], 'daily' => [], 'logs' => []]]);
        }

        $end = now()->toDateString();
        $start = now()->subDays(min(90, max(1, $request->integer('days', 30))))->toDateString();

        try {
            $usage = $liteLlm->usage($credential->external_user_id, $start, $end);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'LiteLLM gateway data is temporarily unavailable.'], 502);
        }
        $credential->update([
            'current_spend' => data_get($usage, 'summary.total_spend', data_get($usage, 'summary.spend', $credential->current_spend)),
            'last_synced_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $usage]);
    }
}
