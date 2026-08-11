<?php

namespace App\Http\Controllers\System\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user()->load(['roles.permissions']);
        
        $permissions = $user->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');

        $groupedPermissions = $permissions->groupBy(
            fn ($permission): string => str($permission->name)->beforeLast('.')->toString()
        );

        $notifications = \App\Models\SystemNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.profile.index', compact('user', 'groupedPermissions', 'notifications'));
    }

    public function updateInfo(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
            'title' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'title' => $request->title,
            'bio' => $request->bio,
        ];

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            
            $uploadPath = public_path('uploads/avatars');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $file->move($uploadPath, $fileName);
            $updateData['avatar'] = 'uploads/avatars/' . $fileName;
        }

        $user->update($updateData);

        audit_log('Updated personal profile details');

        return response()->json([
            'success' => true,
            'message' => 'Profile information updated successfully!',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'title' => $user->title ?? 'User',
            ],
            'redirect' => route('v1.profile.index')
        ]);
    }

    public function updatePassword(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->getRawOriginal('password'))) {
            return response()->json([
                'success'  => false,
                'message'  => 'Current password does not match our records.',
                'redirect' => route('v1.profile.index')
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        audit_log('Changed account password');
        send_notification(
            'Password Changed',
            'Your account password was updated successfully.',
            route('v1.profile.index')
        );

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully!',
            'redirect' => route('v1.profile.index')
        ]);
    }
}
