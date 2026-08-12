<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\LarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LarkSsoController extends Controller
{
    public function redirect(Request $request, LarkService $lark): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('lark_oauth_state', $state);

        try {
            return redirect()->away($lark->authorizationUrl($state));
        } catch (Throwable $exception) {
            Log::warning('Lark SSO redirect failed', ['message' => $exception->getMessage()]);

            return to_route('login')->with('error', 'Lark SSO is not configured.');
        }
    }

    public function callback(Request $request, LarkService $lark): RedirectResponse
    {
        $state = $request->session()->pull('lark_oauth_state');

        if (!$request->filled('code') || !is_string($state) || !hash_equals($state, (string) $request->query('state'))) {
            return to_route('login')->with('error', 'Lark SSO verification failed. Please try again.');
        }

        try {
            $profile = $lark->userFromCode($request->string('code')->toString());
            $openId = $profile['open_id'] ?? null;
            $email = $profile['email'] ?? $profile['enterprise_email'] ?? null;

            if (blank($openId) || blank($email)) {
                return to_route('login')->with('error', 'Your Lark account must provide an email address to sign in.');
            }

            if (! $this->isEmailDomainAllowed($email)) {
                return to_route('login')->with('error', 'Your Lark account email domain is not allowed to sign in.');
            }

            $user = User::where('lark_open_id', $openId)->first() ?? User::where('email', $email)->first();

            if ($user) {
                $user->update([
                    'name' => $profile['name'] ?? $user->name,
                    'email' => $email,
                    'lark_open_id' => $openId,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                $user = User::create([
                    'name' => $profile['name'] ?? 'Lark User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                    'lark_open_id' => $openId,
                ]);

                if ($role = Role::where('name', 'User')->first()) {
                    $user->assignRole($role);
                }
            }

            $request->session()->put('auth_via', 'lark');
            Auth::login($user, true);
            $request->session()->regenerate();

            return to_route('v1.dashboard');
        } catch (Throwable $exception) {
            Log::warning('Lark SSO callback failed', ['message' => $exception->getMessage()]);

            return to_route('login')->with('error', 'Lark SSO could not complete. Please try again.');
        }
    }

    public function isEmailDomainAllowed(string $email): bool
    {
        $allowed = config('services.lark.allowed_domains');

        if (blank($allowed)) {
            return true;
        }

        $domain = mb_strtolower(Str::after($email, '@'));

        if (blank($domain) || ! str_contains($email, '@')) {
            return false;
        }

        return collect(explode(',', $allowed))
            ->map(fn (string $item) => mb_strtolower(trim($item)))
            ->filter()
            ->contains($domain);
    }
}
