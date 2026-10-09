<header class="topbar">
    <div class="container-fluid">
        <div class="navbar-header">
            <div class="d-flex align-items-center">
                <!-- Menu Toggle Button -->
                <div class="topbar-item">
                    <button type="button" class="button-toggle-menu me-2">
                        <iconify-icon icon="solar:hamburger-menu-broken" class="fs-24 align-middle"></iconify-icon>
                    </button>
                </div>

                <!-- Page Title -->
                <div class="topbar-item">
                    <h4 class="fw-bold topbar-button pe-none text-uppercase mb-0">{{ $title ?? 'Dashboard' }}</h4>
                </div>
            </div>

            <div class="d-flex align-items-center gap-1">

                <!-- Theme Toggle (Light/Dark) -->
                <div class="topbar-item">
                    <button type="button" class="topbar-button" id="light-dark-mode">
                        <iconify-icon icon="solar:moon-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                    </button>
                </div>

                <!-- Notifications -->
                <div class="dropdown topbar-item">
                    <button type="button" class="topbar-button position-relative"
                        id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <iconify-icon icon="solar:bell-bing-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                        <span
                            class="position-absolute topbar-badge fs-10 translate-middle badge bg-danger rounded-pill">3<span
                                class="visually-hidden">unread messages</span></span>
                    </button>
                    <div class="dropdown-menu py-0 dropdown-lg dropdown-menu-end"
                        aria-labelledby="page-header-notifications-dropdown">
                        <div class="p-3 border-top-0 border-start-0 border-end-0 border-dashed border">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="m-0 fs-16 fw-semibold">Notifications</h6>
                                </div>
                                <div class="col-auto">
                                    <a href="javascript: void(0);" class="text-dark text-decoration-underline">
                                        <small>Clear All</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div data-simplebar style="max-height: 280px;">
                            <div class="text-center p-4">
                                <p class="text-muted mb-0">No new notifications</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="dropdown topbar-item">
                    <a type="button" class="topbar-button" id="page-header-user-dropdown" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle" width="32"
                                src="{{ auth()->user()->avatar ?? asset('assets/images/users/avatar-1.jpg') }}"
                                alt="avatar">
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="page-header-user-dropdown">
                        <!-- item-->
                        <h6 class="dropdown-header">Welcome {{ auth()->user()->name }}!</h6>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bx bx-user-circle text-muted fs-18 align-middle me-1"></i>
                            <span class="align-middle">Profile</span>
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="bx bx-cog text-muted fs-18 align-middle me-1"></i>
                            <span class="align-middle">Settings</span>
                        </a>
                        <div class="dropdown-divider my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="bx bx-log-out text-muted fs-18 align-middle me-1"></i>
                                <span class="align-middle">Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
