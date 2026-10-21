{{--
    Error pages (403, 404, 419, 429, 500…). Standalone on purpose: no session,
    database or signed-in user is needed, so a page still renders when one of
    those is what failed.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title')</title>
    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />
</head>

<body class="h-100">
    <main class="d-flex flex-column h-100 align-items-center justify-content-center p-3 text-center">
        <p class="display-4 fw-bold text-primary mb-2">@yield('code')</p>
        <h1 class="h4 mb-3">@yield('title')</h1>
        <p class="text-muted mb-4" style="max-width: 32rem;">@yield('message')</p>

        <div class="d-flex flex-wrap gap-2 justify-content-center">
            @section('actions')
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-light">{{ __('Go back') }}</a>
                <a href="{{ url('/dashboard') }}" class="btn btn-primary">{{ __('Go to the dashboard') }}</a>
            @show
        </div>
    </main>
</body>

</html>
