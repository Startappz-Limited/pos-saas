<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('Verify your email') }}</h4>
        <p class="text-muted">
            {{ __('Thanks for signing up! Before getting started, please verify your email address by clicking on the link we just emailed to you. Your business opens, and its trial starts, once it is verified.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success" role="alert">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <x-ui-button variant="primary" type="submit" class="w-100">
            {{ __('Resend verification email') }}
        </x-ui-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-link text-muted">{{ __('Sign out') }}</button>
    </form>
</x-guest-layout>
