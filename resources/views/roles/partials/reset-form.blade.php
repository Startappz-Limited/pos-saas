{{-- "Reset to default" for a business's copy of a role template (Phase 5). --}}
@can('reset', $role)
    <form action="{{ route('roles.reset', $role) }}" method="POST" class="d-inline" data-swal-confirm
        data-swal-title="{{ __('Reset this role to default?') }}"
        data-swal-text="{{ __('Its permissions will be replaced by the built-in role\'s current permissions, in this business only.') }}"
        data-swal-button="{{ __('Reset to default') }}">
        @csrf
        <input type="hidden" name="confirm" value="0">
        <noscript>
            <div class="form-check text-start mb-2">
                <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-reset-{{ $role->uuid }}">
                <label class="form-check-label" for="confirm-reset-{{ $role->uuid }}">{{ __('Yes, reset this role to default') }}</label>
            </div>
        </noscript>
        <x-ui-button variant="outline-warning" size="sm" type="submit" icon="solar:restart-broken">
            {{ __('Reset to default') }}
        </x-ui-button>
    </form>
    @include('partials.swal-confirm-script')
@endcan
