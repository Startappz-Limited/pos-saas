<x-guest-layout>
    <div class="text-center mb-4">
        @if ($suspended ?? false)
            {{-- Phase 5: the platform suspended the business. --}}
            <h4 class="mb-2">{{ __('This business is suspended') }}</h4>
            <p class="text-muted">
                {{ __('The business you work in has been suspended by the platform. Nobody can work in it until it is reactivated. Contact support for more.') }}
            </p>
        @else
            <h4 class="mb-2">{{ __('No active business') }}</h4>
            <p class="text-muted">
                {{ __('Your account is not a member of any active business. Ask a business owner to invite you, or sign out and create your own business.') }}
            </p>
        @endif
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-ui-button variant="primary" type="submit" class="w-100">
            {{ __('Sign out') }}
        </x-ui-button>
    </form>
</x-guest-layout>
