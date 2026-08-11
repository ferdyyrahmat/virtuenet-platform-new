<?php

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('', [App\Http\Controllers\System\User\UserController::class, 'index'])
            ->middleware('permission:admin.users.index')->name('index');
        Route::get('create', [App\Http\Controllers\System\User\UserController::class, 'create'])
            ->middleware('permission:admin.users.create')->name('create');
        Route::post('store', [App\Http\Controllers\System\User\UserController::class, 'store'])
            ->middleware('permission:admin.users.store')->name('store');
        Route::get('{id}/edit', [App\Http\Controllers\System\User\UserController::class, 'edit'])
            ->middleware('permission:admin.users.edit')->name('edit');
        Route::put('{id}/update', [App\Http\Controllers\System\User\UserController::class, 'update'])
            ->middleware('permission:admin.users.update')->name('update');
        Route::delete('{id}/destroy', [App\Http\Controllers\System\User\UserController::class, 'destroy'])
            ->middleware('permission:admin.users.destroy')->name('destroy');
    });

    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('', [App\Http\Controllers\System\Permission\PermissionController::class, 'index'])
            ->middleware('permission:admin.permissions.index')->name('index');
        Route::get('create', [App\Http\Controllers\System\Permission\PermissionController::class, 'create'])
            ->middleware('permission:admin.permissions.create')->name('create');
        Route::post('store', [App\Http\Controllers\System\Permission\PermissionController::class, 'store'])
            ->middleware('permission:admin.permissions.store')->name('store');
        Route::get('{id}/edit', [App\Http\Controllers\System\Permission\PermissionController::class, 'edit'])
            ->middleware('permission:admin.permissions.edit')->name('edit');
        Route::put('{id}/update', [App\Http\Controllers\System\Permission\PermissionController::class, 'update'])
            ->middleware('permission:admin.permissions.update')->name('update');
        Route::delete('{id}/destroy', [App\Http\Controllers\System\Permission\PermissionController::class, 'destroy'])
            ->middleware('permission:admin.permissions.destroy')->name('destroy');
        Route::patch('{id}/lock', [App\Http\Controllers\System\Permission\PermissionController::class, 'toggleLock'])
            ->middleware('permission:admin.permissions.lock')->name('lock');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('', [App\Http\Controllers\System\Notification\NotificationController::class, 'index'])
            ->middleware('permission:admin.notifications.index')->name('index');
        Route::post('send-blast', [App\Http\Controllers\System\Notification\NotificationController::class, 'sendBlast'])
            ->middleware('permission:admin.notifications.send-blast')->name('send-blast');
    });

    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('', [App\Http\Controllers\System\AuditLog\AuditLogController::class, 'index'])
            ->middleware('permission:admin.audit-logs.index')->name('index');
    });

    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('', [App\Http\Controllers\System\Ticket\TicketController::class, 'index'])
            ->middleware('permission:admin.tickets.index')->name('index');
        Route::get('developers', [App\Http\Controllers\System\Ticket\DeveloperController::class, 'index'])
            ->middleware('permission:admin.tickets.developers.index')->name('developers.index');
        Route::post('developers', [App\Http\Controllers\System\Ticket\DeveloperController::class, 'store'])
            ->middleware('permission:admin.tickets.developers.store')->name('developers.store');
        Route::put('developers/{id}', [App\Http\Controllers\System\Ticket\DeveloperController::class, 'update'])
            ->middleware('permission:admin.tickets.developers.update')->name('developers.update');
        Route::delete('developers/{id}', [App\Http\Controllers\System\Ticket\DeveloperController::class, 'destroy'])
            ->middleware('permission:admin.tickets.developers.destroy')->name('developers.destroy');
        Route::get('{id}', [App\Http\Controllers\System\Ticket\TicketController::class, 'show'])
            ->middleware('permission:admin.tickets.show')->name('show');
        Route::post('{id}/reply', [App\Http\Controllers\System\Ticket\TicketController::class, 'reply'])
            ->middleware('permission:admin.tickets.reply')->name('reply');
        Route::post('{id}/assign', [App\Http\Controllers\System\Ticket\TicketController::class, 'assign'])
            ->middleware('permission:admin.tickets.assign')->name('assign');
        Route::delete('{id}', [App\Http\Controllers\System\Ticket\TicketController::class, 'destroy'])
            ->middleware('permission:admin.tickets.destroy')->name('destroy');
    });

    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('', [App\Http\Controllers\System\Directory\DirectoryController::class, 'index'])
            ->middleware('permission:admin.directory.index')->name('index');
        Route::post('upload', [App\Http\Controllers\System\Directory\DirectoryController::class, 'upload'])
            ->middleware('permission:admin.directory.upload')->name('upload');
        Route::post('folder', [App\Http\Controllers\System\Directory\DirectoryController::class, 'makeFolder'])
            ->middleware('permission:admin.directory.folder')->name('folder');
        Route::get('download', [App\Http\Controllers\System\Directory\DirectoryController::class, 'download'])
            ->middleware('permission:admin.directory.download')->name('download');
        Route::delete('destroy', [App\Http\Controllers\System\Directory\DirectoryController::class, 'destroy'])
            ->middleware('permission:admin.directory.destroy')->name('destroy');
    });
});
