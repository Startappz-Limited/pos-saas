{{-- Till PIN (Phase 7): for signing in on a till that is offline. --}}
<x-ui-card :title="__('Till PIN')">
    <p class="text-muted">
        {{ __('Use this PIN to sign in on a till when it has no connection. It works in :business only; each business you belong to has its own.', ['business' => current_business()?->name]) }}
        @if ($hasTillPin)
            <span class="d-block mt-1"><x-ui-badge variant="success" soft>{{ __('PIN set') }}</x-ui-badge></span>
        @else
            <span class="d-block mt-1"><x-ui-badge variant="secondary" soft>{{ __('No PIN yet') }}</x-ui-badge></span>
        @endif
    </p>

    <form method="POST" action="{{ route('profile.till-pin.update') }}" novalidate>
        @csrf
        @method('PUT')
        @honeypot

        <div class="row g-3">
            <div class="col-sm-6">
                <label for="till_pin" class="form-label">{{ $hasTillPin ? __('New PIN') : __('PIN') }}</label>
                <input type="password" id="till_pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off"
                    class="form-control @error('pin') is-invalid @enderror" aria-describedby="till_pin_help" required>
                @error('pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text" id="till_pin_help">{{ __('4 digits. Avoid 1234, 0000 and your birth year. After 4 wrong tries the till locks you out for a while.') }}</div>
            </div>
            <div class="col-sm-6">
                <label for="till_pin_confirmation" class="form-label">{{ __('PIN again') }}</label>
                <input type="password" id="till_pin_confirmation" name="pin_confirmation" inputmode="numeric" maxlength="4" autocomplete="off"
                    class="form-control" required>
            </div>
            <div class="col-sm-6">
                <label for="till_pin_password" class="form-label">{{ __('Your password') }}</label>
                <input type="password" id="till_pin_password" name="current_password" autocomplete="current-password"
                    class="form-control @error('current_password') is-invalid @enderror" required>
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <x-ui-button variant="primary" type="submit" class="mt-3">{{ $hasTillPin ? __('Change PIN') : __('Set PIN') }}</x-ui-button>
    </form>
</x-ui-card>
