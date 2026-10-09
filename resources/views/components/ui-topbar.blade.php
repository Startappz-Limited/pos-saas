{{--
    UI Component: Topbar
    
    Usage: <x-ui-topbar :title="$title" />
    
    Props:
    - title: Page subtitle/title
--}}

<header class="topbar">
    <div class="container-fluid">
        <div class="navbar-header">
            <div class="d-flex align-items-center">
                <!-- Logo -->
                <div class="topbar-brand">
                    <a href="{{ route('dashboard') }}" class="logo">
                        <span class="logo-light">
                            <span class="logo-lg">
                                <img src="{{ asset('assets/images/logo.png') }}" alt="logo" height="22">
                            </span>
                            <span class="logo-sm">
                                <img src="{{ asset('assets/images/logo-sm.png') }}" alt="small logo" height="22">
                            </span>
                        </span>
                        <span class="logo-dark">
                            <span class="logo-lg">
                                <img src="{{ asset('assets/images/logo-dark.png') }}" alt="dark logo" height="22">
                            </span>
                            <span class="logo-sm">
                                <img src="{{ asset('assets/images/logo-sm.png') }}" alt="small logo" height="22">
                            </span>
                        </span>
                    </a>
                </div>

                <!-- Menu Toggle Button -->
                <button type="button"
                    class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger shadow-none"
                    id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <!-- Page Title -->
                @if (isset($title))
                    <div class="d-none d-md-block ms-3">
                        <h4 class="page-title mb-0">{{ $title }}</h4>
                    </div>
                @endif
            </div>

            <div class="d-flex align-items-center gap-1">

                <!-- Search -->
                <div class="dropdown topbar-head-dropdown ms-1 header-item">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-search fs-22"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0">
                        <form class="p-2">
                            <div class="search-box">
                                <input type="text" class="form-control" placeholder="Search...">
                                <i class="bx bx-search search-icon"></i>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="dropdown topbar-head-dropdown ms-1 header-item">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-bell fs-22"></i>
                        <span
                            class="position-absolute topbar-badge translate-middle badge rounded-pill bg-danger">3</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0">
                        <div class="dropdown-head rounded-top">
                            <div class="p-3 border-bottom">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="mb-0">Notifications (3)</h6>
                                    </div>
                                    <div class="col-auto">
                                        <a href="#!" class="small">Mark all as read</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-2 pt-2">
                            <a href="#!" class="text-reset notification-item d-block dropdown-item">
                                <div class="d-flex">
                                    <div class="flex-shrink-0 avatar-xs me-3">
                                        <span class="avatar-title bg-soft-info text-info rounded-circle fs-16">
                                            <i class="bx bx-badge-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mt-0 mb-1 fs-14">Low stock alert</h6>
                                        <div class="text-muted fs-12">
                                            <p class="mb-1">5 products are running low on stock</p>
                                            <p class="mb-0"><i class="mdi mdi-clock-outline"></i> 2 min ago</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="text-center py-3">
                            <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-link">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn shadow-none" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user"
                                src="{{ auth()->user()->avatar ?? asset('assets/images/users/avatar-1.jpg') }}"
                                alt="Header Avatar">
                            <span class="text-start ms-xl-2">
                                <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ auth()->user()->name }}</span>
                                <span
                                    class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">{{ auth()->user()->roles->first()?->name ?? 'User' }}</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Welcome {{ auth()->user()->name }}!</h6>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Profile</span>
                        </a>
                        <a class="dropdown-item" href="{{ route('settings') }}">
                            <i class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle">Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
