<?php

use App\Http\Middleware\CheckLockscreen;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            CheckLockscreen::class,
            SetLocale::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->preventRequestForgery(except: ['webhooks/github']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->ajax() || ! in_array($request->path(), ['login', 'two-factor-challenge'], true)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Unable to authenticate.',
                'redirect' => null,
                'errors' => $exception->errors(),
            ], $exception->status);
        });
    })->create();
