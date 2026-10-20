<section>
    <header class="mb-3">
        <h5 class="mb-1">{{ __('Deactivate account') }}</h5>
        <p class="text-muted mb-0">
            {{ __('Your account is deactivated, not deleted: you are signed out everywhere and can no longer sign in, and what you recorded keeps your name. To work in a business again, ask the platform operator to reactivate your account. If you are the only owner of a business, make someone else an owner first.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <div class="mb-3">
            <label for="deactivate_password" class="form-label">{{ __('Confirm with your password') }}</label>
            <input id="deactivate_password" name="password" type="password" autocomplete="current-password"
                class="form-control @if ($errors->userDeletion->has('password')) is-invalid @endif" required>
            @foreach ($errors->userDeletion->get('password') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach
        </div>

        <button type="submit" class="btn btn-danger">{{ __('Deactivate my account') }}</button>
    </form>
</section>
