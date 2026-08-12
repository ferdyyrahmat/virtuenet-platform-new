<!-- Topbar Start -->
<div class="topbar-custom">
    <div class="container-fluid">
        <div class="d-flex justify-content-between">
            <ul class="list-unstyled topnav-menu mb-0 d-flex align-items-center">
                <li>
                    <button class="button-toggle-menu nav-link">
                        <i data-feather="menu" class="noti-icon"></i>
                    </button>
                </li>
                @php
                    $hour = (int) date('H');
                    if ($hour >= 5 && $hour < 12) {
                        $greeting = __('messages.good_morning');
                    } elseif ($hour >= 12 && $hour < 17) {
                        $greeting = __('messages.good_afternoon');
                    } elseif ($hour >= 17 && $hour < 19) {
                        $greeting = __('messages.good_evening');
                    } else {
                        $greeting = __('messages.good_night');
                    }
                @endphp
                <li class="d-none d-lg-block">
                    <h5 class="mb-0" id="topbar-greeting-text">{{ $greeting }}, {{ auth()->user()->name }}</h5>
                </li>
            </ul>

            <ul class="list-unstyled topnav-menu mb-0 d-flex align-items-center">

                <!-- Global Search Bar -->
                <li class="d-none d-lg-block position-relative me-2">
                    <div class="position-relative topbar-search">
                        <input type="text" id="topbar-search-input" class="form-control bg-light bg-opacity-75 border-light ps-4" placeholder="{{ __('messages.search') }}" autocomplete="off">
                        <i class="mdi mdi-magnify fs-16 position-absolute text-muted top-50 translate-middle-y ms-2"></i>
                    </div>

                    <!-- Search Results Dropdown -->
                    <div id="topbar-search-dropdown" class="dropdown-menu dropdown-menu-start shadow-lg border mt-2 p-0 w-100 overflow-hidden" style="display: none; min-width: 320px; max-height: 400px; overflow-y: auto; z-index: 1050;">
                        <div id="topbar-search-content" class="p-2"></div>
                    </div>
                </li>

                <!-- Quick Feedback Icon Button -->
                <li class="d-none d-sm-flex me-1">
                    <button type="button" class="btn nav-link text-primary" data-bs-toggle="modal" data-bs-target="#global-feedback-modal" title="Submit Feedback or Report Bug">
                        <i class="mdi mdi-message-outline fs-20 align-middle"></i>
                    </button>
                </li>

                <!-- Theme Toggle (Dark/Light Mode) -->
                <li class="d-none d-sm-flex me-1">
                    <button type="button" class="btn nav-link" id="btn-theme-toggle" title="{{ session('theme') === 'dark' ? __('messages.light_mode') : __('messages.dark_mode') }}">
                        <i id="theme-toggle-icon" class="mdi {{ session('theme') === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night' }} fs-20 align-middle"></i>
                    </button>
                </li>

                <!-- Direct Language Switcher Toggle (ID / EN) -->
                <li class="d-none d-sm-flex me-1">
                    <a href="{{ route('lang.switch', app()->getLocale() === 'id' ? 'en' : 'id') }}" class="btn nav-link fw-bold fs-13 d-flex align-items-center" title="{{ app()->getLocale() === 'id' ? 'Switch to English' : 'Ubah ke Bahasa Indonesia' }}">
                        <span class="badge bg-body-secondary text-body border px-2 py-1 fs-12 text-uppercase">{{ app()->getLocale() }}</span>
                    </a>
                </li>

                <li class="d-none d-sm-flex">
                    <button type="button" class="btn nav-link" data-toggle="fullscreen">
                        <i data-feather="maximize" class="align-middle fullscreen noti-icon"></i>
                    </button>
                </li>

                <li class="dropdown notification-list topbar-dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false" id="topbar-bell-btn">
                        <i data-feather="bell" class="noti-icon"></i>
                        <span class="badge bg-danger rounded-circle noti-icon-badge" id="topbar-bell-count" style="display: none;">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-lg shadow-lg border mt-2">

                        <!-- item-->
                        <div class="dropdown-item noti-title">
                            <h5 class="m-0">
                                <span class="float-end">
                                    <a href="javascript:void(0);" id="btn-clear-all-noti" class="text-dark">
                                        <small>{{ __('messages.clear_all') }}</small>
                                    </a>
                                </span>{{ __('messages.notifications') }}
                            </h5>
                        </div>

                        <div class="noti-scroll" id="topbar-notifications-list" data-simplebar style="max-height: 300px; overflow-y: auto;">
                            <div class="text-center py-3 text-muted fs-13">{{ __('messages.loading') }}</div>
                        </div>

                        <!-- All-->
                        <a href="{{ route('v1.profile.index') }}#tab-notifications" class="dropdown-item text-center text-primary notify-item notify-all border-top">
                            {{ __('messages.view_all') }}
                            <i class="mdi mdi-arrow-right ms-1"></i>
                        </a>

                    </div>
                </li>

                <li class="dropdown notification-list topbar-dropdown">
                    <a class="nav-link dropdown-toggle nav-user me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <img src="{{ auth()->user()->avatar_url }}" alt="user-image" class="rounded-circle" width="32" height="32" style="object-fit: cover; aspect-ratio: 1/1;">
                        <span class="pro-user-name ms-1">
                            {{ auth()->user()->name }} <i class="mdi mdi-chevron-down"></i>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end profile-dropdown ">
                        <!-- item-->
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">{{ __('messages.welcome') }} !</h6>
                        </div>

                        <!-- item-->
                        <a href="{{ route('v1.profile.index') }}" class="dropdown-item notify-item">
                            <i class="mdi mdi-account-circle-outline fs-16 align-middle"></i>
                            <span>{{ __('messages.my_account') }}</span>
                        </a>

                        <!-- item-->
                        <a href="{{ route('v1.tickets.index') }}" class="dropdown-item notify-item">
                            <i class="mdi mdi-lifebuoy fs-16 align-middle"></i>
                            <span>{{ __('messages.my_tickets') }}</span>
                        </a>

                        <!-- item-->
                        <form id="lockscreen-form" action="{{ route('lockscreen.lock') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                        <a href="javascript:void(0);" onclick="document.getElementById('lockscreen-form').submit();" class="dropdown-item notify-item">
                            <i class="mdi mdi-lock-outline fs-16 align-middle"></i>
                            <span>{{ __('messages.lock_screen') }}</span>
                        </a>

                        <div class="dropdown-divider"></div>

                        <!-- item-->
                        <a href="{{ route('logout.get') }}" class="dropdown-item notify-item">
                            <i class="mdi mdi-location-exit fs-16 align-middle"></i>
                            <span>{{ __('messages.logout') }}</span>
                        </a>

                    </div>
                </li>

            </ul>
        </div>
    </div>
</div>
<!-- end Topbar -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dark Mode Toggle
        var btnToggle = document.getElementById('btn-theme-toggle');
        if (btnToggle) {
            btnToggle.addEventListener('click', function(e) {
                e.preventDefault();
                var html = document.documentElement;
                var currentTheme = html.getAttribute('data-bs-theme') || 'light';
                var newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                html.setAttribute('data-bs-theme', newTheme);
                document.body.setAttribute('data-menu-color', newTheme);

                var icon = document.getElementById('theme-toggle-icon');
                if (icon) {
                    icon.className = newTheme === 'dark' ? 'mdi mdi-weather-sunny fs-20 align-middle' : 'mdi mdi-weather-night fs-20 align-middle';
                }

                $.ajax({
                    url: "{{ route('theme.toggle') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        theme: newTheme
                    }
                });
            });
        }

        // Live Global Search Bar
        var searchInput = document.getElementById('topbar-search-input');
        var searchDropdown = document.getElementById('topbar-search-dropdown');
        var searchContent = document.getElementById('topbar-search-content');
        var searchTimer = null;

        if (searchInput && searchDropdown && searchContent) {
            searchInput.addEventListener('input', function() {
                var q = this.value.trim();
                clearTimeout(searchTimer);

                if (q.length < 2) {
                    searchDropdown.style.display = 'none';
                    return;
                }

                searchTimer = setTimeout(function() {
                    $.ajax({
                        url: "{{ route('global.search') }}",
                        type: "GET",
                        data: { q: q },
                        success: function(res) {
                            if (res.success && res.results.length > 0) {
                                var html = '';
                                var currentCategory = '';

                                res.results.forEach(function(item) {
                                    if (item.category !== currentCategory) {
                                        currentCategory = item.category;
                                        html += '<div class="dropdown-header text-uppercase fs-11 fw-bold text-muted py-1 px-3 mt-1">' + currentCategory + '</div>';
                                    }

                                    var avatarHtml = item.avatar 
                                        ? '<img src="' + item.avatar + '" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;">'
                                        : '<i class="mdi ' + (item.icon || 'mdi-magnify') + ' me-2 fs-16 text-primary align-middle"></i>';

                                    html += '<a href="' + item.url + '" class="dropdown-item d-flex align-items-center py-2 px-3 rounded-2 mb-1 search-result-item">';
                                    html += avatarHtml;
                                    html += '<div class="overflow-hidden">';
                                    html += '<div class="fw-semibold text-body fs-13 text-truncate">' + item.title + '</div>';
                                    html += '<div class="fs-11 text-muted text-truncate">' + item.subtitle + '</div>';
                                    html += '</div>';
                                    html += '</a>';
                                });

                                searchContent.innerHTML = html;
                                searchDropdown.style.display = 'block';
                            } else {
                                searchContent.innerHTML = '<div class="p-3 text-center text-muted fs-13"><i class="mdi mdi-alert-circle-outline me-1"></i>No results found</div>';
                                searchDropdown.style.display = 'block';
                            }
                        }
                    });
                }, 250);
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                // Hide dropdown on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        searchDropdown.style.display = 'none';
                    }
                });
            });
        }
            // Live Notifications Bell AJAX Polling & Actions
        function fetchNotifications() {
            $.ajax({
                url: "{{ route('notifications.bell.index') }}",
                type: "GET",
                success: function(res) {
                    var countBadge = $('#topbar-bell-count');
                    if (res.unreadCount > 0) {
                        countBadge.text(res.unreadCount > 99 ? '99+' : res.unreadCount).show();
                    } else {
                        countBadge.hide();
                    }

                    var listContainer = $('#topbar-notifications-list');
                    if (res.notifications.length === 0) {
                        listContainer.html('<div class="p-3 text-center text-muted fs-13"><i class="mdi mdi-bell-off-outline me-1"></i>No notifications yet</div>');
                        return;
                    }

                    var html = '';
                    res.notifications.forEach(function(item) {
                        var bgClass = item.is_read ? '' : 'bg-light bg-opacity-50';
                        var badgeDot = item.is_read ? '' : '<span class="badge bg-danger rounded-circle p-1 ms-2" style="width: 8px; height: 8px;"> </span>';

                        html += '<div class="dropdown-item notify-item py-2 px-3 border-bottom position-relative ' + bgClass + '">';
                        html += '<div class="d-flex align-items-start">';
                        html += '<div class="notify-icon me-2 mt-1" onclick="markNotificationRead(' + item.id + ', \'' + item.url + '\')" style="cursor: pointer;">';
                        html += '<i class="mdi ' + item.icon + ' fs-20 text-primary"></i>';
                        html += '</div>';
                        html += '<div class="flex-grow-1 overflow-hidden" onclick="markNotificationRead(' + item.id + ', \'' + item.url + '\')" style="cursor: pointer;">';
                        html += '<div class="d-flex align-items-center justify-content-between mb-1 pe-3">';
                        html += '<span class="fw-semibold text-dark fs-13 text-truncate">' + item.title + '</span>';
                        html += '<div class="d-flex align-items-center"><small class="text-muted fs-11">' + item.time_ago + '</small>' + badgeDot + '</div>';
                        html += '</div>';
                        html += '<p class="mb-0 text-muted fs-12 text-wrap" style="line-height: 1.3;">' + item.message + '</p>';
                        html += '</div>';
                        html += '<button type="button" class="btn btn-sm text-muted p-0 ms-1 position-absolute top-0 end-0 me-2 mt-2 border-0" onclick="deleteSingleNotification(event, ' + item.id + ')" title="Remove notification" style="background: transparent;">';
                        html += '<i class="mdi mdi-close fs-14"></i>';
                        html += '</button>';
                        html += '</div>';
                        html += '</div>';
                    });

                    listContainer.html(html);
                }
            });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 15000); // Poll every 15 seconds

        window.markNotificationRead = function(id, targetUrl) {
            $.ajax({
                url: "/notifications-bell/" + id + "/read",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function() {
                    if (targetUrl && targetUrl !== 'javascript:void(0);') {
                        window.location.href = targetUrl;
                    } else {
                        fetchNotifications();
                    }
                }
            });
        };

        window.deleteSingleNotification = function(event, id) {
            event.stopPropagation();
            event.preventDefault();
            $.ajax({
                url: "/notifications-bell/" + id,
                type: "DELETE",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function() {
                    $('#noti-item-' + id).fadeOut(300, function() {
                        $(this).remove();
                        if ($('#profile-noti-container .noti-profile-item').length === 0) {
                            $('#profile-noti-container').html('<div class="text-center py-5"><i class="mdi mdi-bell-off-outline text-muted fs-36"></i><p class="text-muted fs-14 mt-2 mb-0">No notifications found in your inbox.</p></div>');
                            $('#profile-noti-badge').hide();
                        }
                    });
                    if (typeof fetchNotifications === 'function') {
                        fetchNotifications();
                    }
                }
            });
        };

        $(document).on('click', '#btn-clear-all-noti', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $.ajax({
                url: "{{ route('notifications.bell.clear') }}",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function() {
                    fetchNotifications();
                }
            });
        });
    });
</script>
