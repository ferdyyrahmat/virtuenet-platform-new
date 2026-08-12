<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\LarkSsoController;
use App\Http\Controllers\Auth\LockscreenController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/register', [RegisteredUserController::class, 'create'])
    ->middleware('guest')
    ->name('register');

Route::post('/registering', [RegisteredUserController::class, 'store'])
        ->middleware('guest')
        ->name('register.store');

Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->middleware('guest')
    ->name('password.request');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.update');

Route::get('/verify-email', [EmailVerificationPromptController::class, '__invoke'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::get('/lockscreen', [LockscreenController::class, 'show'])
    ->middleware('auth')
    ->name('lockscreen');

Route::post('/lockscreen/lock', [LockscreenController::class, 'lock'])
    ->middleware('auth')
    ->name('lockscreen.lock');

Route::post('/lockscreen/unlock', [LockscreenController::class, 'unlock'])
    ->middleware('auth')
    ->name('lockscreen.unlock');

Route::get('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout.get');

Route::get('/auth/lark/redirect', [LarkSsoController::class, 'redirect'])
    ->middleware('guest')
    ->name('lark.redirect');

Route::get('/auth/lark/callback', [LarkSsoController::class, 'callback'])
    ->middleware('guest')
    ->name('lark.callback');
