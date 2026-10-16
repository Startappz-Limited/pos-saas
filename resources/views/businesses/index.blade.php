<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('Choose a business') }}</h4>
        <p class="text-muted">{{ __('Pick the business to work in. You can switch at any time from the top bar.') }}</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    <div class="list-group mb-4">
        @foreach ($businesses as $business)
            <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">{{ $business->name }}</div>
                    @if ($business->id === $currentId)
                        <span class="badge bg-success-subtle text-success">{{ __('Current') }}</span>
                    @endif
                    @if ($business->id === $defaultId)
                        <span class="badge bg-primary-subtle text-primary">{{ __('Default') }}</span>
                    @endif
                </div>
                <div class="d-flex gap-1">
                    @if ($business->id !== $defaultId)
                        <form method="POST" action="{{ route('businesses.default', $business) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-light">{{ __('Make default') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('businesses.switch', $business) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Open') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-link text-muted">{{ __('Sign out') }}</button>
    </form>
</x-guest-layout>
