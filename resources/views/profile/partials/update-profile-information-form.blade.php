<x-ui-card :title="__('Profile Information')" class="mb-3">
    <p class="text-muted">{{ __("Update your account's profile information and email address.") }}</p>

    @if (session('status') === 'profile-updated')
        <x-ui-alert variant="success" icon="solar:check-circle-broken" dismissible>
            {{ __('Your profile has been saved.') }}
        </x-ui-alert>
    @endif

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="row">
            <div class="col-lg-6">
                <x-ui-form-input name="name" :label="__('Name')" :value="old('name', $user->name)" required autofocus
                    autocomplete="name" />
            </div>

            <div class="col-lg-6">
                <x-ui-form-input name="email" type="email" :label="__('Email')" :value="old('email', $user->email)" required
                    autocomplete="username" />
            </div>
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <x-ui-alert variant="warning" icon="solar:letter-broken">
                {{ __('Your email address is unverified.') }}
                <button form="send-verification" class="btn btn-link p-0 align-baseline">
                    {{ __('Click here to re-send the verification email.') }}
                </button>

                @if (session('status') === 'verification-link-sent')
                    <div class="mt-1 fw-medium">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </div>
                @endif
            </x-ui-alert>
        @endif

        <div class="text-end">
            <x-ui-button type="submit" icon="solar:diskette-broken">{{ __('Save') }}</x-ui-button>
        </div>
    </form>
</x-ui-card>
