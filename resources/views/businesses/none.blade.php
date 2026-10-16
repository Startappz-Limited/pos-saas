<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('No active business') }}</h4>
        <p class="text-muted">
            {{ __('Your account is not a member of any active business. Ask a business owner to invite you, or sign out and create your own business.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-ui-button variant="primary" type="submit" class="w-100">
            {{ __('Sign out') }}
        </x-ui-button>
    </form>
</x-guest-layout>
