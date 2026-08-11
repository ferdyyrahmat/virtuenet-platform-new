<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckLockscreen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && session('user_is_locked') === true) {
            $routeName = $request->route() ? $request->route()->getName() : '';

            $exceptRoutes = ['lockscreen', 'lockscreen.lock', 'lockscreen.unlock', 'logout', 'logout.get', 'lark.redirect', 'lark.callback'];

            if (!in_array($routeName, $exceptRoutes)) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Screen is locked. Please enter your password to unlock.',
                        'redirect' => route('lockscreen')
                    ], 423);
                }

                return redirect()->route('lockscreen');
            }
        }

        return $next($request);
    }
}
