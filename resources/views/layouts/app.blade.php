<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name'))</title>

    <!-- Vendor css (Bootstrap & Vendors) -->
    <link href="{{ theme_asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons css (Iconify & Boxicons) -->
    <link href="{{ theme_asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css (Theme Styles) -->
    <link href="{{ theme_asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Theme Config js (Require in all Page) -->
    <script src="{{ theme_asset('assets/js/config.js') }}"></script>

    @stack('styles')
</head>

<body>
    <!-- START Wrapper -->
    <div class="wrapper">

        @include('partials.topbar')
        @include('partials.sidebar')

        <!-- ==================================================== -->
        <!-- Start right Content here -->
        <!-- ==================================================== -->
        <div class="page-content">

            <!-- Start Container Fluid -->
            <div class="container-fluid">

                <!-- Breadcrumb -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">@yield('title', 'Dashboard')</h4>
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                @if (!request()->routeIs('dashboard'))
                                    <li class="breadcrumb-item active">@yield('title', 'Page')</li>
                                @endif
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                @include('partials.flash-messages')

                <!-- Page Content -->
                @yield('content')

            </div>
            <!-- End Container Fluid -->

            <!-- Footer -->
            @include('partials.footer')

        </div>
        <!-- End Page content -->

    </div>
    <!-- END Wrapper -->

    <!-- Vendor Javascript (Require in all Page) -->
    <script src="{{ theme_asset('assets/js/vendor.js') }}"></script>

    <!-- App Javascript (Require in all Page) -->
    <script src="{{ theme_asset('assets/js/app.js') }}"></script>

    <!-- Layout Javascript (Menu & Theme Customizer) -->
    <script src="{{ theme_asset('assets/js/layout.js') }}"></script>

    @stack('scripts')
    @stack('modals')

</body>

</html>
