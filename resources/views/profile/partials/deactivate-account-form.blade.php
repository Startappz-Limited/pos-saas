{{--
    Staff and co-admins can deactivate their own account; only the business
    owner deletes accounts (from Users), and the owner's own account can only go
    by closing the business. A super-admin's account is managed by another
    super-admin.
--}}
<section>
    <h2 class="fs-18 fw-semibold mb-1">{{ __('Deactivate account') }}</h2>

    @if ($user->isSuperAdmin())
        <p class="text-muted mb-0">
            {{ __('Platform administrator accounts cannot be deactivated from here. Another super-admin can deactivate this account.') }}
        </p>
    @elseif ($user->isBusinessOwner())
        <p class="text-muted mb-0">
            {{ __('You own :business, so your account cannot be deactivated or deleted on its own: the business depends on it. Closing the business, which permanently deletes all of its data, will be offered here.', ['business' => $user->business?->name]) }}
        </p>
    @else
        <p class="text-muted">
            {{ __('Deactivating signs you out everywhere and stops you signing in. Your sales and other work stay with the business. Only an administrator can reactivate your account, and only the business owner can delete it.') }}
        </p>

        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateAccountModal">
            {{ __('Deactivate my account') }}
        </button>

        <div class="modal fade" id="deactivateAccountModal" tabindex="-1" aria-labelledby="deactivateAccountTitle" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('profile.deactivate') }}" class="modal-content">
                    @csrf
                    @honeypot

                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivateAccountTitle">{{ __('Deactivate your account?') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>

                    <div class="modal-body">
                        <p>{{ __('You will be signed out straight away and will not be able to sign in again until an administrator reactivates you.') }}</p>

                        <label for="deactivate_password" class="form-label">{{ __('Enter your password to confirm') }}</label>
                        <input type="password" id="deactivate_password" name="password" autocomplete="current-password"
                            class="form-control @error('password', 'userDeactivation') is-invalid @enderror" required>
                        @error('password', 'userDeactivation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Deactivate my account') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->userDeactivation->isNotEmpty())
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        new bootstrap.Modal(document.getElementById('deactivateAccountModal')).show();
                    });
                </script>
            @endpush
        @endif
    @endif
</section>
