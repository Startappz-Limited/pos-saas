<x-guest-layout>
    <div class="text-center text-md-start">
        <div class="avatar-md bg-warning-subtle rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
            <i class="bx bx-store-alt fs-1 text-warning" aria-hidden="true"></i>
        </div>

        <h2 class="fw-bold fs-24 mb-2">{{ __('You are not linked to a shop yet') }}</h2>

        <p class="text-muted mb-4">
            {{ __('Hi :name, your account is active but it has not been linked to a shop. Please contact your administrator and ask them to link your account to the shop you work in. Once they have, select "Check again" to continue.', ['name' => auth()->user()->name]) }}
        </p>

        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
            <a href="{{ route('no-shop') }}" class="btn btn-primary">
                <i class="bx bx-refresh me-1" aria-hidden="true"></i>{{ __('Check again') }}
            </a>

            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bx bx-log-out me-1" aria-hidden="true"></i>{{ __('Sign out') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
