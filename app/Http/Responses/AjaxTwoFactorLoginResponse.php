<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class AjaxTwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication successful.',
                'redirect' => $request->session()->pull('url.intended', Fortify::redirects('login')),
            ]);
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
