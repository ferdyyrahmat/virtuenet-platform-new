<?php

namespace App\Http\Controllers\System\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationBlast;
use App\Models\Role;
use App\Models\SystemNotification;
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

        $users = match ($validated['target_type']) {
            'role' => User::whereHas('roles', fn ($query) => $query->whereKey($validated['target_id']))->get(),
            'user' => User::whereKey($validated['target_id'])->get(),
            default => User::all(),
        };

        foreach ($users as $user) {
            SystemNotification::send(
                $user,
                $validated['title'],
                $validated['message'],
                $validated['type'],
                'mdi-bell-outline',
                $validated['url'] ?? null,
            );
        }

        $blast = NotificationBlast::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'channels' => ['in_app'],
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'] ?? null,
            'type' => $validated['type'],
            'status' => 'sent',
            'sent_count' => $users->count(),
            'failed_count' => 0,
            'created_by' => Auth::id(),
        ]);

        audit_log("Dispatched in-app notification blast #{$blast->id}", 'create', 'notification');

        return response()->json([
            'success' => true,
            'message' => "In-app notification sent to {$users->count()} user(s).",
            'redirect' => route('admin.notifications.index'),
        ]);
    }
}
