<?php

use App\Http\Controllers\Auth\ImpersonationController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\System\Language\LanguageController;
use App\Http\Controllers\System\Notification\NotificationBellController;
use App\Http\Controllers\System\Search\SearchController;
use App\Http\Controllers\Webhook\GithubWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

require __DIR__.'/auth.php';
require __DIR__.'/partials/admin.php';
require __DIR__.'/partials/user.php';

Route::post('webhooks/github', GithubWebhookController::class)->name('webhooks.github');

Route::get('lang/{lang}', [LanguageController::class, 'switchLang'])->name('lang.switch');
Route::post('theme/toggle', [LanguageController::class, 'toggleTheme'])->name('theme.toggle');
Route::get('global-search', [SearchController::class, 'search'])->middleware(['auth'])->name('global.search');
Route::middleware(['auth'])->prefix('impersonation')->name('impersonation.')->group(function () {
    Route::post('start/{user}', [ImpersonationController::class, 'start'])->name('start');
    Route::post('stop', [ImpersonationController::class, 'stop'])->name('stop');
});
Route::middleware(['auth'])->prefix('notifications-bell')->name('notifications.bell.')->group(function () {
    Route::get('', [NotificationBellController::class, 'getNotifications'])->name('index');
    Route::post('{id}/read', [NotificationBellController::class, 'markAsRead'])->name('read');
    Route::delete('{id}', [NotificationBellController::class, 'destroy'])->name('destroy');
    Route::post('clear-all', [NotificationBellController::class, 'clearAll'])->name('clear');
});
Route::get('errors/{code}', function ($code) {
    if (view()->exists("errors.{$code}")) {
        return response()->view("errors.{$code}");
    }
    abort((int) $code);
})->name('error.show');

Route::get('', [RoutingController::class, 'index'])->middleware(['auth'])->name('root');
Route::prefix('v1')->name('v1.')->middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
