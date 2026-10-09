<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <!-- Vendor css (Bootstrap & Vendors) -->
    <link href="{{ theme_asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons css (Iconify & Boxicons) -->
    <link href="{{ theme_asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css (Theme Styles) -->
    <link href="{{ theme_asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    @stack('styles')
</head>

<body class="h-100">
    <div class="d-flex flex-column h-100 p-3">
        <div class="d-flex flex-column flex-grow-1">
            <div class="row h-100">
                <div class="col-xxl-7">
                    <div class="row justify-content-center h-100">
                        <div class="col-lg-6 py-lg-5">
                            <div class="d-flex flex-column h-100 justify-content-center">
                                <div class="auth-logo mb-4">
                                    <a href="{{ url('/') }}" class="logo-dark">
                                        <img src="{{ asset('assets/images/logo-dark.png') }}" height="24"
                                            alt="logo dark">
                                    </a>

                                    <a href="{{ url('/') }}" class="logo-light">
                                        <img src="{{ asset('assets/images/logo-light.png') }}" height="24"
                                            alt="logo light">
                                    </a>
                                </div>

                                {{ $slot }}

                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-5 d-none d-xxl-flex">
                    <div class="card h-100 mb-0 overflow-hidden">
                        <div class="d-flex flex-column h-100">
                            <img src="{{ asset('assets/images/small/img-10.jpg') }}" alt=""
                                class="w-100 h-100 object-fit-cover">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor Javascript -->
    <script src="{{ theme_asset('assets/js/vendor.js') }}"></script>

    <!-- App Javascript -->
    <script src="{{ theme_asset('assets/js/app.js') }}"></script>

    @stack('scripts')
</body>

</html>
