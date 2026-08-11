<!-- Left Sidebar Start -->
<div class="app-sidebar-menu">
    <div class="h-100" data-simplebar>

        <!--- Sidemenu -->
        <div id="sidebar-menu">

            <div class="logo-box">
                <a href="{{ route('root')}}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="/images/logo-sm.png" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="/images/logo-light.png" alt="" height="24">
                    </span>
                </a>
                <a href="{{ route('root')}}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="/images/logo-sm.png" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="/images/logo-dark.png" alt="" height="24">
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

                <li>
                    <a href="{{ route('v1.tickets.index') }}" class="tp-link">
                        <i data-feather="life-buoy"></i>
                        <span> {{ __('messages.my_tickets') }} </span>
                    </a>
                </li>

                @include('layouts.partials.sidebar.admin')
            </ul>

        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
</div>
<!-- Left Sidebar End -->
