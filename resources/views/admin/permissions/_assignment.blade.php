@php($selectedPermissions = $rolePermissions ?? [])

<div class="row mb-3 align-items-end">
    <div class="col-lg-6 mb-3 mb-lg-0">
        <h5 class="m-0 fw-semibold">Permissions for this role</h5>
        <p class="text-muted fs-13 mb-0 mt-1">
            Assign permissions to <strong>{{ $role->name ?? 'this role' }}</strong>.
            Each permission uses a <code>name</code> and <code>guard_name</code>.
        </p>
    </div>
    <div class="col-lg-6">
        <div class="d-flex gap-2 justify-content-lg-end">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Search</span>
                <input type="search" id="permission-search" class="form-control" placeholder="Permission name...">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="check-all-global">Select all permissions</button>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 d-flex align-items-start gap-2 py-2 mb-3">
    <i class="mdi mdi-information-outline fs-18"></i>
    <div class="fs-13">
        <strong>Permission model</strong> stores these records by <code>name</code> under the <code>web</code> guard.
        Technical route details are available only when needed.
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
    @foreach($groupedPermissions as $group => $permissions)
        <div class="col permission-group-card">
            <div class="card h-100 border border-light-subtle shadow-sm transition-all hover-shadow">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                    <div>
                        <span class="d-block text-muted fs-11">Permission group</span>
                        <code class="text-primary fw-semibold">{{ $group }}</code>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input select-all-group" type="checkbox" id="select_all_{{ Str::slug($group) }}" data-group="{{ Str::slug($group) }}">
                        <label class="form-check-label fs-11 text-muted" for="select_all_{{ Str::slug($group) }}">Select all</label>
                    </div>
                </div>
                <div class="card-body py-3">
                    @foreach($permissions as $permission)
                        <div class="form-check mb-3 permission-item">
                            <input class="form-check-input group-item-{{ Str::slug($group) }}" type="checkbox" name="permissions[]" value="{{ $permission['name'] }}" id="perm_{{ Str::slug($permission['name']) }}" {{ in_array($permission['name'], $selectedPermissions) ? 'checked' : '' }}>
                            <label class="form-check-label fs-13" for="perm_{{ Str::slug($permission['name']) }}">
                                <span class="badge bg-light text-muted fs-9">Permission name</span>
                                <strong class="text-dark font-monospace d-block mt-1">{{ $permission['name'] }}</strong>
                                <span class="text-muted d-block fs-11">guard_name: web</span>
                            </label>
                            <details class="ms-4 mt-1 text-muted fs-11">
                                <summary>Technical reference</summary>
                                <span class="d-block mt-1">{{ $permission['method'] }} {{ $permission['uri'] }}</span>
                            </details>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
