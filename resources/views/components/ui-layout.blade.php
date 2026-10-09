{{--
    UI Component: Layout
    
    Usage:
    <x-ui-layout title="Page Title" :breadcrumbs="['Home', 'Products']">
        Your content here
    </x-ui-layout>
    
    Props:
    - title: Page title
    - breadcrumbs: Array of breadcrumb items
    - class: Additional CSS classes
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }} - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Additional Styles -->
    @stack('styles')
</head>

<body>
    <!-- START Wrapper -->
    <div class="wrapper">

        <!-- Topbar -->
        <x-ui-topbar :title="$title ?? ''" />

        <!-- Sidebar -->
        <x-ui-sidebar />

        <!-- ==================================================== -->
        <!-- Start right Content here -->
        <!-- ==================================================== -->
        <div class="page-content">

            <!-- Start Container Fluid -->
            <div class="container-fluid">

                <!-- Breadcrumb -->
                @if (isset($breadcrumbs))
                    <x-ui-breadcrumb :items="$breadcrumbs" />
                @endif

                <!-- Flash Messages -->
                @include('partials.flash-messages')

                <!-- Page Content -->
                <div class="{{ $class ?? '' }}">
                    {{ $slot }}
                </div>

            </div>
            <!-- End Container Fluid -->

        </div>
        <!-- ==================================================== -->
        <!-- End Page Content -->
        <!-- ==================================================== -->

    </div>
    <!-- END Wrapper -->

    <!-- Scripts -->
    @stack('scripts')
</body>

</html>
