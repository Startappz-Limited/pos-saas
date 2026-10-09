<x-ui-card :title="__('Update Password')" class="mb-3">
    <p class="text-muted">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>

    @if (session('status') === 'password-updated')
        <x-ui-alert variant="success" icon="solar:check-circle-broken" dismissible>
            {{ __('Your password has been changed.') }}
        </x-ui-alert>
    @endif

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="row">
            <div class="col-lg-6">
                <x-ui-form-input name="current_password" id="update_password_current_password" type="password"
                    :label="__('Current Password')" error-bag="updatePassword" autocomplete="current-password" />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <x-ui-form-input name="password" id="update_password_password" type="password" :label="__('New Password')"
                    error-bag="updatePassword" autocomplete="new-password" />
            </div>

            <div class="col-lg-6">
                <x-ui-form-input name="password_confirmation" id="update_password_password_confirmation" type="password"
                    :label="__('Confirm Password')" error-bag="updatePassword" autocomplete="new-password" />
            </div>
        </div>

        <div class="text-end">
            <x-ui-button type="submit" icon="solar:lock-password-broken">{{ __('Update Password') }}</x-ui-button>
        </div>
    </form>
</x-ui-card>
