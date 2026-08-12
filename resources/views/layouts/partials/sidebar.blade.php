<!-- Left Sidebar Start -->
<div class="app-sidebar-menu">
    <div class="h-100" data-simplebar>

        <!--- Sidemenu -->
        <div id="sidebar-menu">

            <div class="logo-box">
                <a href="{{ route('root')}}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="/images/logo-sm.png" alt="Virtuenet Platform" height="34">
                    </span>
                    <span class="logo-lg">
                        <img src="/images/logo-light.png" alt="Virtuenet Platform" height="34">
                    </span>
                </a>
                <a href="{{ route('root')}}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="/images/logo-sm.png" alt="Virtuenet Platform" height="34">
                    </span>
                    <span class="logo-lg">
                        <img src="/images/logo-dark.png" alt="Virtuenet Platform" height="34">
                    </span>
                </a>
            </div>

            <ul id="side-menu">
                <li>
                    <a href="{{ route('root')}}" class="tp-link">
                        <i data-feather="home"></i>
                        <span> {{ __('messages.dashboard') }} </span>
                    </a>
                </li>

                <li><a href="{{ route('v1.requests.index') }}" class="tp-link"><i data-feather="clipboard"></i><span>My requests</span></a></li>
                <li><a href="{{ route('v1.services.index') }}" class="tp-link"><i data-feather="grid"></i><span>Services</span></a></li>
                <li><a href="{{ route('v1.subscriptions.index') }}" class="tp-link"><i data-feather="credit-card"></i><span>My subscriptions</span></a></li>
                <li><a href="{{ auth()->user()->can('view ai usage') ? route('admin.ai-usage.index') : route('v1.ai-usage.index') }}" class="tp-link"><i data-feather="activity"></i><span>AI Monitoring</span></a></li>
                <li><a href="{{ route('v1.tickets.index') }}" class="tp-link"><i data-feather="life-buoy"></i><span>{{ __('messages.my_tickets') }}</span></a></li>

                @include('layouts.partials.sidebar.admin')
            </ul>

        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
</div>
<!-- Left Sidebar End -->
