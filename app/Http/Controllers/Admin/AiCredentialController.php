<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAccessCredential;
use App\Services\AiCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiCredentialController extends Controller
{
    public function status(Request $request, AiAccessCredential $credential, AiCredentialService $service): JsonResponse
    {
        $this->authorize('manage', $credential->request);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'paused', 'revoked'])]]);
        $service->changeStatus($credential, $validated['status']);

        return $this->success('AI credential status synchronized.', $credential);
    }

    public function update(Request $request, AiAccessCredential $credential, AiCredentialService $service): JsonResponse
    {
        $this->authorize('manage', $credential->request);
        $validated = $request->validate([
            'models' => ['sometimes', 'array', 'min:1'],
            'models.*' => ['string', 'max:255'],
            'max_budget' => ['sometimes', 'numeric', 'min:0'],
            'rpm_limit' => ['sometimes', 'integer', 'min:1'],
            'tpm_limit' => ['sometimes', 'integer', 'min:1'],
        ]);
        $service->updateQuota($credential, $validated);

        return $this->success('AI credential limits synchronized.', $credential);
    }

    public function rotate(Request $request, AiAccessCredential $credential, AiCredentialService $service): JsonResponse
    {
        $this->authorize('manage', $credential->request);
        $service->rotate($credential);

        return $this->success('AI credential rotated. Copy the replacement key now.', $credential);
    }

    private function success(string $message, AiAccessCredential $credential): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'redirect' => route('admin.requests.show', $credential->service_request_id),
        ]);
    }
}
