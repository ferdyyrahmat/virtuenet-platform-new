<?php

use App\Http\Controllers\Admin\AiCredentialController;
use App\Http\Controllers\Admin\AiUsageController as AdminAiUsageController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\ConnectionController;
use App\Http\Controllers\Admin\ServiceRequestController as AdminServiceRequestController;
use App\Http\Controllers\System\AuditLog\AuditLogController;
use App\Http\Controllers\System\Directory\DirectoryController;
use App\Http\Controllers\System\Notification\NotificationController;
use App\Http\Controllers\System\Permission\PermissionController;
use App\Http\Controllers\System\Ticket\DeveloperController;
use App\Http\Controllers\System\Ticket\TicketController;
use App\Http\Controllers\System\User\UserController;

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('ai-usage', [AdminAiUsageController::class, 'index'])->middleware('permission:view ai usage')->name('ai-usage.index');
    Route::get('ai-usage/data', [AdminAiUsageController::class, 'data'])->middleware('permission:view ai usage')->name('ai-usage.data');
    Route::prefix('ai-credentials')->name('ai-credentials.')->middleware('permission:manage service requests')->group(function () {
        Route::patch('{credential}/status', [AiCredentialController::class, 'status'])->name('status');
        Route::put('{credential}', [AiCredentialController::class, 'update'])->name('update');
        Route::post('{credential}/rotate', [AiCredentialController::class, 'rotate'])->name('rotate');
    });
    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('', [AdminServiceRequestController::class, 'index'])->middleware('permission:view service requests')->name('index');
        Route::get('{serviceRequest}', [AdminServiceRequestController::class, 'show'])->middleware('permission:view service requests')->name('show');
        Route::post('{serviceRequest}/review', [AdminServiceRequestController::class, 'review'])->middleware('permission:review service requests')->name('review');
        Route::post('{serviceRequest}/transition', [AdminServiceRequestController::class, 'transition'])->middleware('permission:manage service requests')->name('transition');
        Route::put('{serviceRequest}/delivery', [AdminServiceRequestController::class, 'delivery'])->middleware('permission:manage service requests')->name('delivery');
        Route::post('{serviceRequest}/provision-ai', [AdminServiceRequestController::class, 'provision'])->middleware('permission:manage service requests')->name('provision-ai');
    });

    Route::prefix('connections')->name('connections.')->middleware('permission:manage integrations')->group(function () {
        Route::get('', [ConnectionController::class, 'index'])->name('index');
        Route::put('{provider}', [ConnectionController::class, 'update'])->name('update');
        Route::post('{provider}/test', [ConnectionController::class, 'test'])->name('test');
    });

    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('', [ApplicationController::class, 'index'])->middleware('permission:view delivery tasks')->name('index');
        Route::post('', [ApplicationController::class, 'upsert'])->middleware('permission:manage integrations')->name('upsert');
        Route::delete('', [ApplicationController::class, 'destroy'])->middleware('permission:manage integrations')->name('destroy');
        Route::post('sync', [ApplicationController::class, 'sync'])->middleware('permission:sync delivery tasks')->name('sync');
        Route::post('sync-inventory', [ApplicationController::class, 'syncInventory'])->middleware('permission:manage integrations')->name('sync-inventory');
        Route::post('nodes', [ApplicationController::class, 'upsertNode'])->middleware('permission:manage integrations')->name('nodes.upsert');
        Route::delete('nodes/{node}', [ApplicationController::class, 'destroyNode'])->middleware('permission:manage integrations')->name('nodes.destroy');
    });
    Route::redirect('github-sync', '/admin/applications')->name('github-sync.redirect');
    Route::redirect('github-tasks', '/admin/applications')->name('github-tasks.index');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('', [UserController::class, 'index'])
            ->middleware('permission:view users')->name('index');
        Route::get('create', [UserController::class, 'create'])
            ->middleware('permission:create users')->name('create');
        Route::post('store', [UserController::class, 'store'])
            ->middleware('permission:create users')->name('store');
        Route::get('{id}/edit', [UserController::class, 'edit'])
            ->middleware('permission:edit users')->name('edit');
        Route::put('{id}/update', [UserController::class, 'update'])
            ->middleware('permission:edit users')->name('update');
        Route::delete('{id}/destroy', [UserController::class, 'destroy'])
            ->middleware('permission:delete users')->name('destroy');
    });

    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('', [PermissionController::class, 'index'])
            ->middleware('permission:view roles and permissions')->name('index');
        Route::get('create', [PermissionController::class, 'create'])
            ->middleware('permission:create roles')->name('create');
        Route::post('store', [PermissionController::class, 'store'])
            ->middleware('permission:create roles')->name('store');
        Route::get('{id}/edit', [PermissionController::class, 'edit'])
            ->middleware('permission:edit roles and permissions')->name('edit');
        Route::put('{id}/update', [PermissionController::class, 'update'])
            ->middleware('permission:edit roles and permissions')->name('update');
        Route::delete('{id}/destroy', [PermissionController::class, 'destroy'])
            ->middleware('permission:delete roles')->name('destroy');
        Route::patch('{id}/lock', [PermissionController::class, 'toggleLock'])
            ->middleware('permission:lock roles')->name('lock');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('', [NotificationController::class, 'index'])
            ->middleware('permission:view notifications')->name('index');
        Route::post('send-blast', [NotificationController::class, 'sendBlast'])
            ->middleware('permission:send notification blasts')->name('send-blast');
    });

    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('', [AuditLogController::class, 'index'])
            ->middleware('permission:view audit logs')->name('index');
    });

    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('', [TicketController::class, 'index'])
            ->middleware('permission:view support tickets')->name('index');
        Route::get('developers', [DeveloperController::class, 'index'])
            ->middleware('permission:view ticket developers')->name('developers.index');
        Route::post('developers', [DeveloperController::class, 'store'])
            ->middleware('permission:create ticket developers')->name('developers.store');
        Route::put('developers/{id}', [DeveloperController::class, 'update'])
            ->middleware('permission:edit ticket developers')->name('developers.update');
        Route::delete('developers/{id}', [DeveloperController::class, 'destroy'])
            ->middleware('permission:delete ticket developers')->name('developers.destroy');
        Route::get('{id}', [TicketController::class, 'show'])
            ->middleware('permission:view support tickets')->name('show');
        Route::post('{id}/reply', [TicketController::class, 'reply'])
            ->middleware('permission:reply to support tickets')->name('reply');
        Route::post('{id}/assign', [TicketController::class, 'assign'])
            ->middleware('permission:assign support tickets')->name('assign');
        Route::delete('{id}', [TicketController::class, 'destroy'])
            ->middleware('permission:delete support tickets')->name('destroy');
    });

    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('', [DirectoryController::class, 'index'])
            ->middleware('permission:view directory')->name('index');
        Route::post('upload', [DirectoryController::class, 'upload'])
            ->middleware('permission:upload directory files')->name('upload');
        Route::post('folder', [DirectoryController::class, 'makeFolder'])
            ->middleware('permission:create directory folders')->name('folder');
        Route::get('download', [DirectoryController::class, 'download'])
            ->middleware('permission:download directory files')->name('download');
        Route::delete('destroy', [DirectoryController::class, 'destroy'])
            ->middleware('permission:delete directory items')->name('destroy');
    });
});
