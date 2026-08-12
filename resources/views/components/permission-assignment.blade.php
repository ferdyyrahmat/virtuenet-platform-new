@props([
    'categories',
    'selectedPermissions' => [],
    'roleName' => null,
])

@php
    $categoryMeta = [
        'Roles & Permissions' => ['icon' => 'mdi-shield-key-outline', 'description' => 'Manage roles and their permission assignments.'],
        'User Management' => ['icon' => 'mdi-account-group-outline', 'description' => 'Manage user accounts and access.'],
        'Audit Trail' => ['icon' => 'mdi-history', 'description' => 'Review application activity and changes.'],
        'Support Tickets' => ['icon' => 'mdi-lifebuoy', 'description' => 'Handle tickets, replies, assignment, and ticket developers.'],
        'Notifications' => ['icon' => 'mdi-bell-outline', 'description' => 'View and send in-app notifications.'],
        'Cloud Directory' => ['icon' => 'mdi-folder-network-outline', 'description' => 'Browse and manage files and folders.'],
        'Other' => ['icon' => 'mdi-shield-outline', 'description' => 'Additional application capabilities.'],
    ];
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header bg-light d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2 py-2">
        <div>
            <h5 class="card-title mb-0">Permissions <span class="text-muted fs-13 fw-normal">for {{ $roleName ? "the {$roleName} role" : 'this role' }}</span></h5>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                <input type="search" id="permission-search" class="form-control" placeholder="Search permissions..." aria-label="Search permissions">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="check-all-global">Select all</button>
        </div>
    </div>

    <div class="card-body p-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-1 mb-2">
            <span class="text-muted fs-12">Grouped by application menu.</span>
            <div class="d-flex gap-2">
                <span class="badge bg-light text-dark">Guard: web</span>
                <span class="badge bg-primary-subtle text-primary" id="permission-selection-count">0 selected</span>
            </div>
        </div>

        <div class="row g-2" id="permission-categories">
            @foreach($categories as $category => $permissions)
                @php
                    $slug = Str::slug($category);
                    $meta = $categoryMeta[$category] ?? $categoryMeta['Other'];
                @endphp
                <div class="col-12 col-xl-6 permission-category" data-category="{{ $slug }}">
                    <div class="card h-100 border shadow-none">
                        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center gap-2 py-2 px-3" title="{{ $meta['description'] }}">
                            <div class="d-flex gap-2">
                                <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                                    <i class="mdi {{ $meta['icon'] }} fs-16"></i>
                                </span>
                                <div>
                                    <h6 class="mb-0 fw-semibold fs-14">{{ $category }}</h6>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="form-check form-switch mb-0 d-inline-flex align-items-center gap-1">
                                    <input class="form-check-input category-toggle" type="checkbox" id="category_{{ $slug }}" data-category="{{ $slug }}">
                                    <label class="form-check-label fs-11 text-muted" for="category_{{ $slug }}">All</label>
                                </div>
                                <span class="badge bg-light text-muted category-selection-count">0/{{ $permissions->count() }}</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($permissions as $permission)
                                    <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-3 permission-row" for="permission_{{ $permission->id }}">
                                        <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="permission_{{ $permission->id }}" data-category="{{ $slug }}" {{ in_array($permission->name, $selectedPermissions, true) ? 'checked' : '' }}>
                                        <span class="fw-semibold text-body fs-13 flex-grow-1">{{ ucfirst($permission->name) }}</span>
                                        <i class="mdi mdi-information-outline text-muted fs-15" title="{{ $permission->description ?: 'No description provided.' }}" aria-label="{{ $permission->description ?: 'No description provided.' }}"></i>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="text-center text-muted py-4 d-none" id="permission-empty-state">
            <i class="mdi mdi-shield-outline fs-32"></i>
            <p class="mb-0 mt-1">No permissions match your search.</p>
        </div>
    </div>
</div>
