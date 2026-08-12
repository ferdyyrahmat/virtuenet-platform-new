@php
    $user = auth()->user();
    $can = fn (string $permission): bool => $user?->isDeveloper() || $user?->can($permission);
    $routeName = request()->route()?->getName() ?? '';
    $showPlatformOps = $can('view service requests') || $can('view delivery tasks');
    $showSystem = $can('view roles and permissions') || $can('view users') || $can('manage integrations') || $can('view audit logs') || $can('view directory');
@endphp

@if($showPlatformOps)
<li class="menu-title">Platform operations</li>
<li><a href="#sidebarPlatform" data-bs-toggle="collapse" aria-expanded="{{ Str::startsWith($routeName, ['admin.requests.', 'admin.github-tasks.']) ? 'true' : 'false' }}"><i data-feather="layers"></i><span>Operations</span><span class="menu-arrow"></span></a><div class="collapse {{ Str::startsWith($routeName, ['admin.requests.', 'admin.github-tasks.']) ? 'show' : '' }}" id="sidebarPlatform"><ul class="nav-second-level">@if($can('view service requests'))<li><a href="{{ route('admin.requests.index') }}" class="tp-link">Request queue</a></li>@endif @if($can('view delivery tasks'))<li><a href="{{ route('admin.github-tasks.index') }}" class="tp-link">GitHub–Lark tasks</a></li>@endif</ul></div></li>
@endif

@if($showSystem)
<li class="menu-title">System management</li>
<li><a href="#sidebarSystem" data-bs-toggle="collapse" aria-expanded="{{ Str::startsWith($routeName,'admin.')?'true':'false' }}"><i data-feather="settings"></i><span>Administration</span><span class="menu-arrow"></span></a><div class="collapse {{ Str::startsWith($routeName,'admin.')?'show':'' }}" id="sidebarSystem"><ul class="nav-second-level">
    @if($can('manage integrations'))<li><a href="{{ route('admin.connections.index') }}" class="tp-link">Gateway connections</a></li>@endif
    @if($can('view roles and permissions'))<li><a href="{{ route('admin.permissions.index') }}" class="tp-link">Roles & permissions</a></li>@endif
    @if($can('view users'))<li><a href="{{ route('admin.users.index') }}" class="tp-link">Users</a></li>@endif
    @if($can('view audit logs'))<li><a href="{{ route('admin.audit-logs.index') }}" class="tp-link">Activity log</a></li>@endif
    @if($can('view directory'))<li><a href="{{ route('admin.directory.index') }}" class="tp-link">Cloud directory</a></li>@endif
    @if($user?->isAdmin())<li><a href="{{ url('/pulse') }}" class="tp-link">Laravel Pulse</a></li><li><a href="{{ url('/horizon') }}" class="tp-link">Laravel Horizon</a></li>@endif
</ul></div></li>
@endif

@if($can('view support tickets') || $can('view notifications'))
<li><a href="#sidebarSupport" data-bs-toggle="collapse"><i data-feather="life-buoy"></i><span>Support</span><span class="menu-arrow"></span></a><div class="collapse" id="sidebarSupport"><ul class="nav-second-level">@if($can('view support tickets'))<li><a href="{{ route('admin.tickets.index') }}" class="tp-link">Support tickets</a></li>@endif @if($can('view notifications'))<li><a href="{{ route('admin.notifications.index') }}" class="tp-link">In-app announcements</a></li>@endif</ul></div></li>
@endif
