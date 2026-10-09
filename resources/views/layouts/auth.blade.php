<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Authentication' }} - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="authentication-bg">
    <div class="account-pages pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-4 col-lg-5 col-md-7">
                    <div class="card overflow-hidden">

                        <!-- Logo -->
                        <div class="card-header bg-primary bg-opacity-10 text-center">
                            <a href="{{ route('dashboard') }}" class="d-block py-4">
                                <img src="{{ asset('assets/images/logo.png') }}" alt="logo" height="30">
                            </a>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-4">

                            <!-- Flash Messages -->
                            @include('partials.flash-messages')

                            <!-- Content -->
                            {{ $slot }}

                        </div>

                        <!-- Footer -->
                        @if (isset($footer))
                            <div class="card-footer text-center py-3">
                                {{ $footer }}
                            </div>
                        @endif

                    </div>

                    <!-- Copyright -->
                    <div class="text-center text-muted mt-3">
                        <p class="mb-0">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>

</html>
