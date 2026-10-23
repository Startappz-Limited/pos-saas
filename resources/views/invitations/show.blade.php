<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('Join :business', ['business' => $invite->business->name]) }}</h4>
        <p class="text-muted">
            {{ __('You are invited as :role. This invitation is for :email.', ['role' => $invite->role?->displayName(), 'email' => $invite->email]) }}
        </p>
    </div>


    @if ($user && strtolower($user->email) === $invite->email)
        <form method="POST" action="{{ route('invitations.accept', $token) }}">
            @csrf
            <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Accept the invitation') }}</x-ui-button>
        </form>
    @elseif ($user)
        <div class="alert alert-warning" role="alert">
            {{ __('You are signed in as :email. Sign out, then open the link again and sign in as :invited.', ['email' => $user->email, 'invited' => $invite->email]) }}
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Sign out') }}</x-ui-button>
        </form>
    @elseif ($hasAccount)
        <p class="text-center">{{ __('You already have an account. Sign in to accept.') }}</p>
        <x-ui-button variant="primary" href="{{ route('invitations.sign-in', $token) }}" class="w-100">{{ __('Sign in') }}</x-ui-button>
    @else
        <form method="POST" action="{{ route('invitations.register', $token) }}">
            @csrf
            @honeypot

            <x-ui-form-input name="name" label="{{ __('Full name') }}" :value="old('name', $invite->name)" required :autofocus="! $invite->name" />
            <x-ui-form-input name="password" type="password" label="{{ __('Password') }}" required
                help="{{ __('10 to 128 characters. A few random words make a strong password.') }}" />
            <x-ui-form-input name="password_confirmation" type="password" label="{{ __('Confirm password') }}" required />

            <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Create account and join') }}</x-ui-button>
        </form>
    @endif
</x-guest-layout>
