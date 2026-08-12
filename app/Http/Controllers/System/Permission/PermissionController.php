<?php

namespace App\Http\Controllers\System\Permission;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $query = Role::query()->withCount(['permissions', 'users']);
            if (! Auth::user()->isDeveloper()) {
                $query->whereRaw('LOWER(name) <> ?', ['developer']);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('name', function ($row) {
                    return '<span class="badge bg-light text-primary fs-12">'.e($row->name).'</span>';
                })
                ->editColumn('description', function ($row) {
                    return e($row->description ?? '-');
                })
                ->editColumn('permissions_count', function ($row) {
                    return '<span class="badge bg-light text-info fs-12">'.$row->permissions_count.' Permissions</span>';
                })
                ->editColumn('users_count', function ($row) {
                    return '<span class="badge bg-light text-success fs-12">'.$row->users_count.' Users</span>';
                })
                ->addColumn('lock_status', function ($row) {
                    if (! Auth::user()->isDeveloper()) {
                        return '';
                    }

                    return $row->isLocked()
                        ? '<span class="badge bg-warning-subtle text-warning"><i class="mdi mdi-lock-outline me-1"></i>Locked</span>'
                        : '<span class="badge bg-success-subtle text-success"><i class="mdi mdi-lock-open-outline me-1"></i>Unlocked</span>';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-';
                })
                ->addColumn('actions', function ($row) {
                    if ($row->isLocked()) {
                        $editUrl = route('admin.permissions.edit', $row->id);
                        $unlock = Auth::user()->isDeveloper() && strcasecmp($row->name, 'Developer') !== 0
                            ? '<button type="button" class="btn btn-sm btn-outline-warning" title="Unlock" onclick="toggleRoleLock('.$row->id.', false)"><i class="mdi mdi-lock-open-outline fs-16"></i></button>'
                            : '';

                        return '<div class="text-center"><a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="mdi mdi-square-edit-outline fs-16"></i></a><span class="badge bg-warning-subtle text-warning me-1" title="Locked role"><i class="mdi mdi-lock-outline me-1"></i>Locked</span>'.$unlock.'</div>';
                    }
                    $editUrl = route('admin.permissions.edit', $row->id);
                    $deleteUrl = route('admin.permissions.destroy', $row->id);
                    $lock = Auth::user()->isDeveloper()
                        ? '<button type="button" class="btn btn-sm btn-outline-warning" title="Lock" onclick="toggleRoleLock('.$row->id.', true)"><i class="mdi mdi-lock-outline fs-16"></i></button>'
                        : '';

                    return '
                        <div class="text-center">
                            <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="mdi mdi-square-edit-outline fs-16"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteRole('.$row->id.', \''.$deleteUrl.'\')">
                                <i class="mdi mdi-trash-can-outline fs-16"></i>
                            </button>'.
                            $lock.'
                        </div>
                    ';
                })
                ->rawColumns(['name', 'permissions_count', 'users_count', 'lock_status', 'actions'])
                ->make(true);
        }

        return view('admin.permissions.index');
    }

    public function create()
    {
        $permissionCategories = $this->permissionCategories();
        $users = $this->visibleUsers();

        return view('admin.permissions.create', compact('permissionCategories', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($this->permissionsFromInput($request->input('permissions', [])));

        if ($request->has('users')) {
            $role->users()->sync($request->users);
        }

        audit_log("Created role '{$role->name}'", 'role.create', 'role');

        // Notify assigned users
        foreach ($role->users as $u) {
            SystemNotification::send(
                $u,
                'Role Assigned',
                "You have been assigned to role: {$role->name}.",
                'role_update',
                'mdi-shield-check-outline',
                route('v1.profile.index')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Role and Permissions created successfully!',
            'redirect' => route('admin.permissions.index'),
        ]);
    }

    public function edit($id)
    {
        $role = Role::with('users')->findOrFail($id);
        $this->ensureRoleVisible($role);
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $roleUserIds = $role->users->pluck('id')->toArray();
        $permissionCategories = $this->permissionCategories();
        $users = $this->visibleUsers();

        return view('admin.permissions.edit', compact('role', 'rolePermissions', 'roleUserIds', 'permissionCategories', 'users'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $this->ensureRoleVisible($role);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$id,
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $role->syncPermissions($this->permissionsFromInput($request->input('permissions', [])));

        $role->users()->sync($request->users ?? []);
        $role->load('users');

        audit_log("Updated role '{$role->name}' permissions/users", 'role.update', 'role');

        // Notify all users in this role
        foreach ($role->users as $u) {
            SystemNotification::send(
                $u,
                'Role & Permissions Updated',
                "Your role '{$role->name}' or its granted permissions have been updated.",
                'role_update',
                'mdi-shield-sync-outline',
                route('v1.profile.index')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Role and Permissions updated successfully!',
            'redirect' => route('admin.permissions.index'),
        ]);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $this->ensureRoleVisible($role);
        abort_if($role->isLocked(), 403, 'The Developer role is locked and cannot be deleted.');
        $roleName = $role->name;
        $role->delete();

        audit_log("Deleted role '{$roleName}'", 'role.delete', 'role');

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully!',
            'redirect' => route('admin.permissions.index'),
        ]);
    }

    public function toggleLock(Request $request, $id)
    {
        abort_unless(Auth::user()->isDeveloper(), 403);

        $role = Role::findOrFail($id);
        abort_if(strcasecmp($role->name, 'Developer') === 0, 403, 'The Developer role is always locked.');

        $role->update(['is_locked' => $request->boolean('locked')]);

        return response()->json([
            'success' => true,
            'message' => $role->is_locked ? "Role '{$role->name}' locked." : "Role '{$role->name}' unlocked.",
        ]);
    }

    private function permissions()
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
    }

    private function permissionCategories()
    {
        $order = [
            'Roles & Permissions',
            'User Management',
            'Service Requests',
            'AI Access & Usage',
            'Subscriptions & Finance',
            'Integrations & Delivery',
            'Audit Trail',
            'Support Tickets',
            'Notifications',
            'Cloud Directory',
            'Other',
        ];

        return $this->permissions()
            ->groupBy(fn (Permission $permission): string => match (true) {
                str_contains($permission->name, 'roles') || str_contains($permission->name, 'permissions') => 'Roles & Permissions',
                str_contains($permission->name, 'users') => 'User Management',
                str_contains($permission->name, 'service requests') => 'Service Requests',
                str_contains($permission->name, 'ai usage') => 'AI Access & Usage',
                str_contains($permission->name, 'subscription') || str_contains($permission->name, 'finance') => 'Subscriptions & Finance',
                str_contains($permission->name, 'integrations') || str_contains($permission->name, 'delivery tasks') => 'Integrations & Delivery',
                str_contains($permission->name, 'audit logs') => 'Audit Trail',
                str_contains($permission->name, 'support tickets') || str_contains($permission->name, 'ticket developers') => 'Support Tickets',
                str_contains($permission->name, 'notification') => 'Notifications',
                str_contains($permission->name, 'directory') => 'Cloud Directory',
                default => 'Other',
            })
            ->sortBy(fn ($permissions, string $category): int => array_search($category, $order, true));
    }

    private function permissionsFromInput(array $names): array
    {
        $available = $this->permissions()->keyBy('name');

        return collect($names)
            ->filter(fn ($name): bool => is_string($name) && $available->has($name))
            ->unique()
            ->map(fn (string $name) => $available->get($name))
            ->all();
    }

    private function ensureRoleVisible(Role $role): void
    {
        if ($role->isLocked() && ! Auth::user()->isDeveloper()) {
            abort(404);
        }
    }

    private function visibleUsers()
    {
        return Auth::user()->isDeveloper()
            ? User::all()
            : User::whereDoesntHave('roles', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['developer']))->get();
    }
}
