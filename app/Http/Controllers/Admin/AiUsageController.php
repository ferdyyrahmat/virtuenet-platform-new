<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAccessCredential;
use App\Models\ExternalConnection;
use App\Services\LiteLlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiUsageController extends Controller
{
    public function index(): View
    {
        return view('ai-usage.index', [
            'credentials' => AiAccessCredential::with(['user', 'request'])->latest()->get(),
            'dataUrl' => route('admin.ai-usage.data'),
            'scopeLabel' => 'Organization AI monitoring',
            'gateway' => ExternalConnection::where('provider', 'litellm')->first(),
            'isOrganizationScope' => true,
        ]);
    }

    public function data(Request $request, LiteLlmService $liteLlm): JsonResponse
    {
        $end = now()->toDateString();
        $start = now()->subDays(min(90, max(1, $request->integer('days', 30))))->toDateString();

        try {
            $usage = $liteLlm->usage(null, $start, $end);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'LiteLLM gateway data is temporarily unavailable.'], 502);
        }

        return response()->json(['success' => true, 'data' => $usage]);
    }
}
