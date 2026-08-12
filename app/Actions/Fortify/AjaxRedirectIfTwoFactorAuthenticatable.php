<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class AjaxRedirectIfTwoFactorAuthenticatable extends RedirectIfTwoFactorAuthenticatable
{
    protected function twoFactorChallengeResponse($request, $user)
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication is required.',
                'redirect' => route('two-factor.login'),
            ]);
        }

        return redirect()->route('two-factor.login');
    }
}
