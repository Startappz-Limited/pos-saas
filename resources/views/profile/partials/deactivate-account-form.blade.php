{{--
    Staff and co-admins can deactivate their own account; only the business
    owner deletes accounts (from Users). The owner sees close-business-form
    instead: their account goes when the business is closed. A super-admin's
    account is managed by another super-admin.
--}}
<x-ui-card :title="__('Deactivate account')" class="mb-3">
    @if ($user->isSuperAdmin())
        <p class="text-muted mb-0">
            {{ __('Platform administrator accounts cannot be deactivated from here. Another super-admin can deactivate this account.') }}
        </p>
    @else
        <p class="text-muted">
            {{ __('Deactivating signs you out everywhere and stops you signing in. Your sales and other work stay with the business. Only an administrator can reactivate your account, and only the business owner can delete it.') }}
        </p>

        <div class="text-end">
            <x-ui-button variant="outline-danger" icon="solar:user-block-broken" data-bs-toggle="modal"
                data-bs-target="#deactivateAccountModal">
                {{ __('Deactivate my account') }}
            </x-ui-button>
        </div>

        @push('modals')
            <x-ui-modal id="deactivateAccountModal" :title="__('Deactivate your account?')" centered>
                <form id="deactivateAccountForm" method="POST" action="{{ route('profile.deactivate') }}">
                    @csrf
                    @honeypot

                    <x-ui-alert variant="warning" icon="solar:danger-triangle-broken">
                        {{ __('You will be signed out straight away and will not be able to sign in again until an administrator reactivates you.') }}
                    </x-ui-alert>

                    <x-ui-form-input name="password" id="deactivate_password" type="password"
                        :label="__('Enter your password to confirm')" error-bag="userDeactivation" groupClass="mb-0"
                        autocomplete="current-password" required />
                </form>

                <x-slot:footer>
                    <x-ui-button variant="light" data-bs-dismiss="modal">{{ __('Cancel') }}</x-ui-button>
                    <x-ui-button type="submit" variant="danger" form="deactivateAccountForm">
                        {{ __('Deactivate my account') }}
                    </x-ui-button>
                </x-slot:footer>
            </x-ui-modal>
        @endpush

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
</x-ui-card>
