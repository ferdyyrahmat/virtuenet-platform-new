<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
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

Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])
    ->middleware('auth')
    ->name('password.confirm');

Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store'])
    ->middleware('auth');

Route::get('/lockscreen', [\App\Http\Controllers\Auth\LockscreenController::class, 'show'])
    ->middleware('auth')
    ->name('lockscreen');

Route::post('/lockscreen/lock', [\App\Http\Controllers\Auth\LockscreenController::class, 'lock'])
    ->middleware('auth')
    ->name('lockscreen.lock');

Route::post('/lockscreen/unlock', [\App\Http\Controllers\Auth\LockscreenController::class, 'unlock'])
    ->middleware('auth')
    ->name('lockscreen.unlock');

Route::get('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout.get');

Route::get('/auth/lark/redirect', [\App\Http\Controllers\Auth\LarkSsoController::class, 'redirect'])
    ->middleware('guest')
    ->name('lark.redirect');

Route::get('/auth/lark/callback', [\App\Http\Controllers\Auth\LarkSsoController::class, 'callback'])
    ->middleware('guest')
    ->name('lark.callback');
