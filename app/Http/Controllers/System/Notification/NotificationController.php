<?php

namespace App\Http\Controllers\System\Notification;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationBlastJob;
use App\Models\NotificationBlast;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notification.index', [
            'blasts' => NotificationBlast::with('creator')->latest()->get(),
            'roles' => Role::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function sendBlast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'target_type' => ['required', 'in:all,role,user'],
            'target_id' => ['nullable', 'integer'],
            'type' => ['required', 'in:info,success,warning,danger'],
            'url' => ['nullable', 'url'],
        ]);

        if ($validated['target_type'] === 'role') {
            $request->validate(['target_id' => ['required', 'exists:roles,id']]);
        }

        if ($validated['target_type'] === 'user') {
            $request->validate(['target_id' => ['required', 'exists:users,id']]);
        }

        $userIds = match ($validated['target_type']) {
            'role' => User::whereHas('roles', fn ($query) => $query->whereKey($validated['target_id']))->pluck('id')->all(),
            'user' => User::whereKey($validated['target_id'])->pluck('id')->all(),
            default => User::pluck('id')->all(),
        };

        $blast = NotificationBlast::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'channels' => ['in_app'],
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'] ?? null,
            'type' => $validated['type'],
            'status' => 'queued',
            'sent_count' => 0,
            'failed_count' => 0,
            'created_by' => Auth::id(),
        ]);

        SendNotificationBlastJob::dispatch($blast->id, $userIds);

        audit_log("Queued in-app notification blast #{$blast->id}", 'create', 'notification');

        return response()->json([
            'success' => true,
            'message' => "In-app notification blast queued for " . count($userIds) . " user(s).",
            'redirect' => route('admin.notifications.index'),
        ]);
    }
}
