<?php

namespace App\Http\Controllers\System\Permission;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $query = Role::query()->withCount(['permissions', 'users']);
            if (!Auth::user()->isDeveloper()) {
                $query->exceptDeveloper();
            }
            
            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('name', function ($row) {
                    return '<span class="badge bg-light text-primary fs-12">' . e($row->name) . '</span>';
                })
                ->editColumn('description', function ($row) {
                    return e($row->description ?? '-');
                })
                ->editColumn('permissions_count', function ($row) {
                    return '<span class="badge bg-light text-info fs-12">' . $row->permissions_count . ' Permissions</span>';
                })
                ->editColumn('users_count', function ($row) {
                    return '<span class="badge bg-light text-success fs-12">' . $row->users_count . ' Users</span>';
                })
                ->addColumn('lock_status', function ($row) {
                    if (!Auth::user()->isDeveloper()) return '';
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
                        $unlock = Auth::user()->isDeveloper() && ! $row->isDeveloper()
                            ? '<button type="button" class="btn btn-sm btn-outline-warning" title="Unlock" onclick="toggleRoleLock(' . $row->id . ', false)"><i class="mdi mdi-lock-open-outline fs-16"></i></button>'
                            : '';
                        return '<div class="text-center"><a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="mdi mdi-square-edit-outline fs-16"></i></a><span class="badge bg-warning-subtle text-warning me-1" title="Locked role"><i class="mdi mdi-lock-outline me-1"></i>Locked</span>' . $unlock . '</div>';
                    }
                    $editUrl = route('admin.permissions.edit', $row->id);
                    $deleteUrl = route('admin.permissions.destroy', $row->id);
                    $lock = Auth::user()->isDeveloper()
                        ? '<button type="button" class="btn btn-sm btn-outline-warning" title="Lock" onclick="toggleRoleLock(' . $row->id . ', true)"><i class="mdi mdi-lock-outline fs-16"></i></button>'
                        : '';
                    return '
                        <div class="text-center">
                            <a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="mdi mdi-square-edit-outline fs-16"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteRole(' . $row->id . ', \'' . $deleteUrl . '\')">
                                <i class="mdi mdi-trash-can-outline fs-16"></i>
                            </button>' . 
                            $lock . '
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
        $groupedPermissions = $this->getGroupedPermissions();
        $users = $this->visibleUsers();
        return view('admin.permissions.create', compact('groupedPermissions', 'users'));
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
            'guard_name' => 'web'
        ]);

        $role->syncPermissions($this->permissionsFromInput($request->input('permissions', [])));

        if ($request->has('users')) {
            $role->users()->sync($request->users);
        }

        audit_log("Created role '{$role->name}'", 'role.create', 'role');

        // Notify assigned users
        foreach ($role->users as $u) {
            \App\Models\SystemNotification::send(
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
            'redirect' => route('admin.permissions.index')
        ]);
    }

    public function edit($id)
    {
        $role = Role::with('users')->findOrFail($id);
        $this->ensureRoleVisible($role);
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $roleUserIds = $role->users->pluck('id')->toArray();
        $groupedPermissions = $this->getGroupedPermissions();
        $users = $this->visibleUsers();

        return view('admin.permissions.edit', compact('role', 'rolePermissions', 'roleUserIds', 'groupedPermissions', 'users'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $this->ensureRoleVisible($role);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
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
            \App\Models\SystemNotification::send(
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
            'redirect' => route('admin.permissions.index')
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
            'success'  => true,
            'message'  => 'Role deleted successfully!',
            'redirect' => route('admin.permissions.index')
        ]);
    }

    public function toggleLock(Request $request, $id)
    {
        abort_unless(Auth::user()->isDeveloper(), 403);

        $role = Role::findOrFail($id);
        abort_if($role->isDeveloper(), 403, 'The Developer role is always locked.');

        $role->update(['is_locked' => $request->boolean('locked')]);

        return response()->json([
            'success' => true,
            'message' => $role->is_locked ? "Role '{$role->name}' locked." : "Role '{$role->name}' unlocked.",
        ]);
    }

    private function getGroupedPermissions()
    {
        $routes = Route::getRoutes();
        $groupedPermissions = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name && Str::startsWith($name, 'admin.')) {
                $parts = explode('.', $name);
                
                if (count($parts) >= 3) {
                    $suffix = array_pop($parts);
                    $group = implode('.', $parts);
                } elseif (count($parts) == 2) {
                    $group = $name;
                    $suffix = 'index';
                } else {
                    continue;
                }

                $groupedPermissions[$group][] = [
                    'name' => $name,
                    'suffix' => $suffix,
                    'uri' => $route->uri(),
                    'method' => implode('|', $route->methods())
                ];
            }
        }
        ksort($groupedPermissions);
        return $groupedPermissions;
    }

    private function permissionsFromInput(array $names): array
    {
        $available = collect($this->getGroupedPermissions())
            ->flatten(1)
            ->pluck('name');

        return collect($names)
            ->filter(fn ($name): bool => is_string($name) && $available->contains($name))
            ->unique()
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'))
            ->all();
    }

    private function ensureRoleVisible(Role $role): void
    {
        if ($role->isLocked() && !Auth::user()->isDeveloper()) {
            abort(404);
        }
    }

    private function visibleUsers()
    {
        return Auth::user()->isDeveloper()
            ? User::all()
            : User::whereDoesntHave('roles', fn ($query) => $query->developer())->get();
    }
}
