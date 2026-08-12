<?php

namespace App\Http\Controllers\System\User;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $query = User::query()->with('roles');
            
            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('name', function ($row) {
                    return e($row->name);
                })
                ->editColumn('email', function ($row) {
                    return e($row->email);
                })
                ->addColumn('roles', function ($row) {
                    $roleNames = $row->roles->pluck('name')->toArray();
                    $rolesBadges = '';
                    if (empty($roleNames)) {
                        $rolesBadges = '<span class="badge bg-light text-muted fs-11">No Role</span>';
                    } else {
                        foreach ($roleNames as $name) {
                            $rolesBadges .= '<span class="badge bg-light text-primary fs-11 me-1">' . e($name) . '</span>';
                        }
                    }
                    return $rolesBadges;
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-';
                })
                ->addColumn('actions', function ($row) {
                    $editUrl = route('admin.users.edit', $row->id);
                    $deleteUrl = route('admin.users.destroy', $row->id);
                    $impersonateUrl = route('impersonation.start', $row->id);
                    $impersonateButton = auth()->user()->isDeveloper() && !$row->isDeveloper()
                        ? '<form method="POST" action="' . $impersonateUrl . '" class="d-inline">' . csrf_field() . '<button type="submit" class="btn btn-sm btn-outline-info me-1" title="Impersonate"><i class="mdi mdi-account-switch-outline fs-16"></i></button></form>'
                        : '';
                    return '
                        <div class="text-center">
                            ' . $impersonateButton . '
                            <a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="mdi mdi-square-edit-outline fs-16"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteUser(' . $row->id . ', \'' . $deleteUrl . '\')">
                                <i class="mdi mdi-trash-can-outline fs-16"></i>
                            </button>
                        </div>
                    ';
                })
                ->rawColumns(['roles', 'actions'])
                ->make(true);
        }
        
        return view('admin.users.index');
    }

    public function create()
    {
        $roles = $this->visibleRoles();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $roleIds = $request->roles ?? [];
        $this->guardDeveloperRoleAssignment($roleIds);
        if ($request->has('roles')) {
            $user->syncRoles($roleIds);
        }

        audit_log("Created user '{$user->name}' ({$user->email})", 'user.create', 'user', ['user_id' => $user->id]);

        // Send welcome notification to created user
        $roleNames = $user->roles->pluck('name')->join(', ') ?: 'User';
        \App\Models\SystemNotification::send(
            $user,
            'Account Created',
            "Your account has been created with role(s): {$roleNames}.",
            'success',
            'mdi-account-check-outline',
            route('v1.profile.index')
        );

        return response()->json([
            'success' => true,
            'message' => 'User created successfully!',
            'redirect' => route('admin.users.index')
        ]);
    }

    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $userRoleIds = $user->roles->pluck('id')->toArray();
        $roles = $this->visibleRoles();

        return view('admin.users.edit', compact('user', 'userRoleIds', 'roles'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->guardDeveloperAccountAccess($user);
        $oldRoleIds = $user->roles->pluck('id')->sort()->values()->toArray();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        $newRoles = $request->roles ?? [];
        $this->guardDeveloperRoleAssignment($newRoles);
        if (!Auth::user()->isDeveloper()) {
            $newRoles = array_unique(array_merge(
                $newRoles,
                Role::whereIn('id', $oldRoleIds)->developer()->pluck('id')->all()
            ));
        }
        $user->syncRoles($newRoles);

        $user->load('roles');
        $newRoleNames = $user->roles->pluck('name')->join(', ') ?: 'No Role';

        audit_log("Updated user '{$user->name}'", 'user.update', 'user', [
            'target_user_id' => $user->id,
            'roles' => $newRoleNames
        ]);

        // Send Bell Notification to the target user if roles were changed or account updated
        \App\Models\SystemNotification::send(
            $user,
            'Role & Profile Updated',
            "Your profile/role has been updated by administrator. Your assigned role(s): {$newRoleNames}.",
            'role_update',
            'mdi-shield-account-outline',
            route('v1.profile.index')
        );

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully!',
            'redirect' => route('admin.users.index')
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $actor = Auth::user();

        abort_if($user->id === $actor->id, 403, 'You cannot delete your own account.');

        if ($user->isDeveloper()) {
            abort_if(! $actor->isDeveloper(), 403, 'Only developers can delete developer accounts.');
            abort_if($this->isLastActiveDeveloper(), 403, 'The last developer account cannot be deleted.');
        }

        $deletedName = $user->name;
        $user->delete();

        audit_log("Deleted user '{$deletedName}'", 'user.delete', 'user', ['user_id' => $id]);

        return response()->json([
            'success'  => true,
            'message'  => 'User deleted successfully!',
            'redirect' => route('admin.users.index')
        ]);
    }

    private function visibleRoles()
    {
        return Auth::user()->isDeveloper()
            ? Role::all()
            : Role::exceptDeveloper()->get();
    }

    private function guardDeveloperRoleAssignment(array $roleIds): void
    {
        if (!Auth::user()->isDeveloper() && Role::whereIn('id', $roleIds)->developer()->exists()) {
            abort(403, 'Only developers can assign the Developer role.');
        }
    }

    private function guardDeveloperAccountAccess(User $user): void
    {
        if (! Auth::user()->isDeveloper() && $user->isDeveloper()) {
            abort(403, 'Only developers can modify developer accounts.');
        }
    }

    private function isLastActiveDeveloper(): bool
    {
        return User::whereHas('roles', fn ($q) => $q->developer())->count() <= 1;
    }
}
