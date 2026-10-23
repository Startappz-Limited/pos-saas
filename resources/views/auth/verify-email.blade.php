<x-guest-layout>
    @php
        $user = auth()->user();
        $expired = $user->mustVerifyEmailNow();
    @endphp

    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('Verify your email') }}</h4>
        <p class="text-muted">
            @if ($expired)
                {{ __('Your 24 hours to verify have run out, so your account is paused. Nothing is lost: verify :email and you carry on where you left off.', ['email' => $user->email]) }}
            @else
                {{ __('We sent a link to :email. Your account works now; please verify within :hours hours.', ['email' => $user->email, 'hours' => $user->verificationHoursLeft() ?? 24]) }}
            @endif
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success" role="alert">
            {{ __('A new verification link has been sent to :email. Older links no longer work.', ['email' => $user->email]) }}
        </div>
    @endif

    @if (session('status') == 'verification-email-changed')
        <div class="alert alert-success" role="alert">
            {{ __('Your email is now :email. We sent a verification link to it and told the old address.', ['email' => $user->email]) }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        @honeypot
        <x-ui-button variant="primary" type="submit" class="w-100">
            {{ __('Resend verification email') }}
        </x-ui-button>
    </form>

    <details class="mb-3" @if ($errors->changeEmail->any()) open @endif>
        <summary class="text-primary">{{ __('Wrong email address? Change it') }}</summary>
        <form method="POST" action="{{ route('verification.email.update') }}" class="mt-3">
            @csrf
            @honeypot
            @method('PUT')
            <div class="mb-2">
                <label class="form-label" for="new_email">{{ __('New email address') }}</label>
                <input type="email" name="email" id="new_email" value="{{ old('email') }}" required
                    class="form-control @error('email', 'changeEmail') is-invalid @enderror">
                @error('email', 'changeEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-2">
                <label class="form-label" for="current_password">{{ __('Your password') }}</label>
                <input type="password" name="current_password" id="current_password" required autocomplete="current-password"
                    class="form-control @error('current_password', 'changeEmail') is-invalid @enderror">
                @error('current_password', 'changeEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <x-ui-button variant="outline-primary" type="submit">{{ __('Change email') }}</x-ui-button>
        </form>
    </details>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-link text-muted">{{ __('Sign out') }}</button>
    </form>
</x-guest-layout>
