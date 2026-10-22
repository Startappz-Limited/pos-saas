{{--
    The platform console layout (Phase 5): for platform operators, outside
    every business. Same theme as the business layout, its own menu.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('Platform console')) · {{ config('app.name') }}</title>

    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <script src="{{ asset('assets/js/config.js') }}"></script>

    @stack('styles')
</head>

<body>
    <div class="wrapper">

        <header class="topbar">
            <div class="container-fluid">
                <div class="navbar-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="topbar-item">
                            <button type="button" class="button-toggle-menu me-2" aria-label="{{ __('Menu') }}">
                                <iconify-icon icon="solar:hamburger-menu-broken" class="fs-24 align-middle"></iconify-icon>
                            </button>
                        </div>
                        <span class="badge bg-dark-subtle text-dark fs-12">{{ __('Platform console') }}</span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small d-none d-md-inline">{{ auth()->user()->name }} · {{ auth()->user()->email }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                <iconify-icon icon="solar:logout-3-broken" class="align-middle"></iconify-icon>
                                {{ __('Sign out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="main-nav">
            <div class="logo-box">
                <a href="{{ route('platform.dashboard') }}" class="logo-dark">
                    <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
                    <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="logo dark">
                </a>
                <a href="{{ route('platform.dashboard') }}" class="logo-light">
                    <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
                    <img src="{{ asset('assets/images/logo-light.png') }}" class="logo-lg" alt="logo light">
                </a>
            </div>

            <div class="scrollbar" data-simplebar>
                <ul class="navbar-nav" id="navbar-nav">
                    <li class="menu-title">{{ __('Platform') }}</li>

                    @foreach ([
                        ['platform.dashboard', 'platform.dashboard', 'solar:widget-5-bold-duotone', __('Overview')],
                        ['platform.businesses.index', 'platform.businesses.*', 'solar:shop-2-bold-duotone', __('Businesses')],
                        ['platform.roles.index', 'platform.roles.*', 'solar:shield-user-bold-duotone', __('Role templates')],
                    ] as [$route, $pattern, $icon, $label])
                        @if (Route::has($route))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">
                                    <span class="nav-icon"><iconify-icon icon="{{ $icon }}"></iconify-icon></span>
                                    <span class="nav-text">{{ $label }}</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">@yield('title', __('Platform console'))</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('platform.dashboard') }}">{{ __('Platform') }}</a></li>
                                @hasSection('breadcrumb')
                                    @yield('breadcrumb')
                                @endif
                            </ol>
                        </div>
                    </div>
                </div>

                @include('partials.flash-messages')

                @yield('content')
            </div>

            @include('partials.footer')
        </div>
    </div>

    <script src="{{ asset('assets/js/vendor.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script src="{{ asset('assets/js/layout.js') }}"></script>

    @stack('scripts')

    @include('partials.form-errors-script')
    @stack('modals')
</body>

</html>
