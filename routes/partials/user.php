<?php

Route::prefix('v1')->name('v1.')->middleware(['auth'])->group(function () {
   Route::prefix('profile')->name('profile.')->group(function () {
      Route::get('', [App\Http\Controllers\System\Profile\ProfileController::class, 'index'])->name('index');
      Route::post('info', [App\Http\Controllers\System\Profile\ProfileController::class, 'updateInfo'])->name('update-info');
      Route::post('password', [App\Http\Controllers\System\Profile\ProfileController::class, 'updatePassword'])->name('update-password');
      
      // Sanctum API Tokens Routes
      Route::post('tokens', [\App\Http\Controllers\System\Profile\SanctumTokenController::class, 'store'])->name('tokens.store');
      Route::delete('tokens/{id}', [\App\Http\Controllers\System\Profile\SanctumTokenController::class, 'destroy'])->name('tokens.destroy');
   });

   Route::prefix('tickets')->name('tickets.')->group(function () {
      Route::get('', [\App\Http\Controllers\User\UserTicketController::class, 'index'])->name('index');
      Route::post('store', [\App\Http\Controllers\User\UserTicketController::class, 'store'])->name('store');
      Route::get('{code}', [\App\Http\Controllers\User\UserTicketController::class, 'show'])->name('show');
      Route::post('{code}/reply', [\App\Http\Controllers\User\UserTicketController::class, 'reply'])->name('reply');
   });
});
