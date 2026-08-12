<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends BaseController
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $developer = Auth::user();

        abort_unless($developer && $developer->isDeveloper(), 403);
        abort_if($user->isDeveloper() || $user->id === $developer->id, 403, 'Developer accounts cannot be impersonated.');

        // Rotate the session before switching accounts, then write the marker
        // before authenticating so the login event listener can detect the
        // impersonation and avoid recording a separate "login" audit entry.
        $request->session()->regenerate();
        $request->session()->put('impersonation', [
            'original_user_id' => $developer->id,
            'original_user_name' => $developer->name,
        ]);
        Auth::login($user);
        $request->session()->save();

        audit_log(
            'Impersonation started',
            'auth.impersonate',
            'auth',
            [
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'original_user_id' => $developer->id,
                'original_user_name' => $developer->name,
            ],
            $developer
        );

        return redirect()->route('v1.dashboard')->with('success', "You are now viewing the application as {$user->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->get('impersonation.original_user_id');
        abort_unless($originalId, 403, 'No active impersonation session.');

        $original = User::findOrFail($originalId);
        abort_unless($original->isDeveloper(), 403, 'The original account is no longer a developer.');

        Auth::login($original);

        audit_log(
            'Impersonation ended',
            'auth.impersonate',
            'auth',
            [
                'original_user_id' => $original->id,
                'original_user_name' => $original->name,
            ],
            $original
        );

        $request->session()->forget('impersonation');
        $request->session()->regenerate();

        return redirect()->route('v1.dashboard')->with('success', 'Impersonation ended. You are back in your developer account.');
    }
}
