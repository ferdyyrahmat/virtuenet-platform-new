<?php

use App\Http\Controllers\RequestAttachmentController;
use App\Http\Controllers\System\Profile\ProfileController;
use App\Http\Controllers\System\Profile\SanctumTokenController;
use App\Http\Controllers\User\AiCredentialController;
use App\Http\Controllers\User\AiUsageController;
use App\Http\Controllers\User\ServiceCatalogController;
use App\Http\Controllers\User\ServiceRequestController;
use App\Http\Controllers\User\SubscriptionController;
use App\Http\Controllers\User\UserTicketController;

Route::prefix('v1')->name('v1.')->middleware(['auth'])->group(function () {
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('', [ProfileController::class, 'index'])->name('index');
        Route::post('info', [ProfileController::class, 'updateInfo'])->name('update-info');
        Route::post('password', [ProfileController::class, 'updatePassword'])->name('update-password');

        // Sanctum API Tokens Routes
        Route::post('tokens', [SanctumTokenController::class, 'store'])->name('tokens.store');
        Route::delete('tokens/{id}', [SanctumTokenController::class, 'destroy'])->name('tokens.destroy');
    });

    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('', [UserTicketController::class, 'index'])->name('index');
        Route::post('store', [UserTicketController::class, 'store'])->name('store');
        Route::get('{code}', [UserTicketController::class, 'show'])->name('show');
        Route::post('{code}/reply', [UserTicketController::class, 'reply'])->name('reply');
    });

    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('', [ServiceRequestController::class, 'index'])->name('index');
        Route::get('create', [ServiceRequestController::class, 'create'])->name('create');
        Route::post('', [ServiceRequestController::class, 'store'])->name('store');
        Route::get('{serviceRequest}', [ServiceRequestController::class, 'show'])->name('show');
        Route::put('{serviceRequest}/resubmit', [ServiceRequestController::class, 'resubmit'])->name('resubmit');
        Route::post('{serviceRequest}/comments', [ServiceRequestController::class, 'comment'])->name('comments.store');
        Route::post('{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('cancel');
    });
    Route::get('request-attachments/{attachment}', RequestAttachmentController::class)->name('request-attachments.download');

    Route::get('ai-usage', [AiUsageController::class, 'index'])->name('ai-usage.index');
    Route::get('ai-usage/data', [AiUsageController::class, 'data'])->name('ai-usage.data');
    Route::get('services', [ServiceCatalogController::class, 'index'])->name('services.index');
    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('ai-credentials/{credential}/reveal', [AiCredentialController::class, 'reveal'])->name('ai-credentials.reveal');
});

Route::get('dashboard/my-services', [ServiceCatalogController::class, 'index'])->middleware('auth')->name('dashboard.my-services');
Route::get('subscription-evidence/{evidence}', [App\Http\Controllers\Admin\SubscriptionController::class, 'evidence'])->middleware('auth')->name('subscription-evidence.download');
