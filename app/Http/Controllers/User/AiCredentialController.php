<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiAccessCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiCredentialController extends Controller
{
    public function reveal(Request $request, AiAccessCredential $credential): JsonResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 403);

        $key = DB::transaction(function () use ($credential): string {
            $credential = AiAccessCredential::query()->lockForUpdate()->findOrFail($credential->id);
            if ($credential->status !== 'active' || $credential->revealed_at || ! $credential->reveal_expires_at?->isFuture()) {
                throw ValidationException::withMessages([
                    'credential' => 'This one-time key is no longer available. Ask an AI administrator to rotate it.',
                ]);
            }

            $key = $credential->virtual_key;
            $credential->update(['revealed_at' => now()]);

            return $key;
        });

        return response()->json([
            'success' => true,
            'message' => 'Copy this key now. It will not be shown again.',
            'key' => $key,
            'redirect' => null,
        ])->header('Cache-Control', 'no-store, private');
    }
}
