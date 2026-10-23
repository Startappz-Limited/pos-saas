<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('This invitation is no longer valid') }}</h4>
        <p class="text-muted">
            {{ __('It may have expired, been revoked or already been used. Ask your admin to send a new invite.') }}
        </p>
    </div>

    <x-ui-button variant="primary" href="{{ route('login') }}" class="w-100">{{ __('Go to sign in') }}</x-ui-button>
</x-guest-layout>
