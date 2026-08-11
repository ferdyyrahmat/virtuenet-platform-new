@php
    /** Load the package-managed permission names once for menu visibility. */
    $user = auth()->user();
    $isDeveloper = $user && $user->isDeveloper();

    $allowedPermissions = $user?->getAllPermissions()->pluck('name')->flip() ?? collect();

    $canAccess = function(string $permission) use ($isDeveloper, $allowedPermissions) {
        if ($isDeveloper) return true;
        return $allowedPermissions->has($permission);
    };

    // Group visibility checks
    $showAccessControl = $canAccess('admin.permissions.index') || $canAccess('admin.users.index');
    $showOperations = $canAccess('admin.audit-logs.index') || $user?->isAdmin();
    $showCommunication = $canAccess('admin.tickets.index') || $canAccess('admin.notifications.index');
    $showInfrastructure = $canAccess('admin.directory.index') || $user?->isAdmin();
    $hasAnyAdminAccess = $showAccessControl || $showOperations || $showCommunication || $showInfrastructure;

    // Active route detection for auto-expanding the correct submenu
    $currentRoute = request()->route() ? request()->route()->getName() : '';
@endphp

@if($hasAnyAdminAccess)
    <li class="menu-title">{{ __('messages.system_management') }}</li>
@endif

@if($isDeveloper)
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- ACCESS CONTROL: Roles & Permissions, User Management --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($showAccessControl)
<li>
    <a href="#sidebarAccessControl" data-bs-toggle="collapse" aria-expanded="{{ Str::startsWith($currentRoute, 'admin.permissions.') || Str::startsWith($currentRoute, 'admin.users.') ? 'true' : 'false' }}" aria-controls="sidebarAccessControl">
        <i data-feather="shield"></i>
        <span> {{ __('messages.access_control') }} </span>
        <span class="menu-arrow"></span>
    </a>
    <div class="collapse {{ Str::startsWith($currentRoute, 'admin.permissions.') || Str::startsWith($currentRoute, 'admin.users.') ? 'show' : '' }}" id="sidebarAccessControl">
        <ul class="nav-second-level">
            @if($canAccess('admin.permissions.index'))
            <li>
                <a href="{{ route('admin.permissions.index') }}" class="tp-link">{{ __('messages.roles_permissions') }}</a>
            </li>
            @endif
            @if($canAccess('admin.users.index'))
            <li>
                <a href="{{ route('admin.users.index') }}" class="tp-link">{{ __('messages.user_management') }}</a>
            </li>
            @endif
        </ul>
    </div>
</li>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- OPERATIONS: Horizon, Pulse, Audit Trail --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($showOperations)
<li>
    <a href="#sidebarOperations" data-bs-toggle="collapse" aria-expanded="{{ Str::startsWith($currentRoute, 'admin.audit-logs.') ? 'true' : 'false' }}" aria-controls="sidebarOperations">
        <i data-feather="server"></i>
        <span> {{ __('messages.operations') }} </span>
        <span class="menu-arrow"></span>
    </a>
    <div class="collapse {{ Str::startsWith($currentRoute, 'admin.audit-logs.') ? 'show' : '' }}" id="sidebarOperations">
        <ul class="nav-second-level">
            @if($user?->isAdmin())
            <li>
                <a href="{{ url('/horizon') }}" class="tp-link"><i data-feather="activity"></i><span>Horizon</span></a>
            </li>
            <li><a href="{{ url('/pulse') }}" class="tp-link"><i data-feather="activity"></i><span>Pulse</span></a></li>
            @endif
            @if($canAccess('admin.audit-logs.index'))
            <li><a href="{{ route('admin.audit-logs.index') }}" class="tp-link">{{ __('messages.audit_trail') }}</a>
            </li>
            @endif
        </ul>
    </div>
</li>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- COMMUNICATION: Support Tickets, Notification Blast --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($showCommunication)
<li>
    <a href="#sidebarCommunication" data-bs-toggle="collapse" aria-expanded="{{ Str::startsWith($currentRoute, 'admin.tickets.') || Str::startsWith($currentRoute, 'admin.notifications.') ? 'true' : 'false' }}" aria-controls="sidebarCommunication">
        <i data-feather="message-circle"></i>
        <span> {{ __('messages.communication') }} </span>
        <span class="menu-arrow"></span>
    </a>
    <div class="collapse {{ Str::startsWith($currentRoute, 'admin.tickets.') || Str::startsWith($currentRoute, 'admin.notifications.') ? 'show' : '' }}" id="sidebarCommunication">
        <ul class="nav-second-level">
            @if($canAccess('admin.tickets.index'))
            <li>
                <a href="{{ route('admin.tickets.index') }}" class="tp-link">{{ __('messages.support_tickets') }}</a>
            </li>
            @endif
            @if($canAccess('admin.notifications.index'))
            <li>
                <a href="{{ route('admin.notifications.index') }}" class="tp-link">{{ __('messages.notification_blast') }}</a>
            </li>
            @endif
        </ul>
    </div>
</li>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- INFRASTRUCTURE: Laravel Filesystem Directory --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($showInfrastructure)
<li>
    <a href="#sidebarInfrastructure" data-bs-toggle="collapse" aria-expanded="{{ Str::startsWith($currentRoute, 'admin.directory.') ? 'true' : 'false' }}" aria-controls="sidebarInfrastructure">
        <i data-feather="hard-drive"></i>
        <span> {{ __('messages.infrastructure') }} </span>
        <span class="menu-arrow"></span>
    </a>
    <div class="collapse {{ Str::startsWith($currentRoute, 'admin.directory.') ? 'show' : '' }}" id="sidebarInfrastructure">
        <ul class="nav-second-level">
            @if($canAccess('admin.directory.index'))
            <li>
                <a href="{{ route('admin.directory.index') }}" class="tp-link">{{ __('messages.cloud_directory') }}</a>
            </li>
            @endif
        </ul>
    </div>
</li>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- SETTINGS: API Documentation --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($hasAnyAdminAccess)
<li>
    <a href="#sidebarSettings" data-bs-toggle="collapse" aria-expanded="false" aria-controls="sidebarSettings">
        <i data-feather="settings"></i>
        <span> {{ __('messages.settings') }} </span>
        <span class="menu-arrow"></span>
    </a>
    <div class="collapse" id="sidebarSettings">
        <ul class="nav-second-level">
            <li>
                <a href="{{ url('/api/documentation') }}" target="_blank" class="tp-link">{{ __('messages.api_docs') }}</a>
            </li>
        </ul>
    </div>
</li>
@endif
