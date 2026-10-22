{{--
    Switch a role on or off (Phase 5). Switching it off is confirmed in a
    SweetAlert dialog naming how many people in this business hold the role;
    switching it back on needs no confirmation. Expects $role and $holders.
--}}
@can('update', $role)
    <form action="{{ route('roles.active', $role) }}" method="POST" class="d-inline"
        @if ($role->is_active)
            data-swal-confirm
            data-swal-title="{{ __('Switch this role off?') }}"
            data-swal-text="{{ __('People with this role won\'t be able to do anything in the system with it until it\'s switched back on.') }} {{ trans_choice('{0} Nobody in this business holds it.|{1} :count person in this business holds it.|[2,*] :count people in this business hold it.', $holders, ['count' => $holders]) }}"
            data-swal-button="{{ __('Switch off') }}"
        @endif>
        @csrf
        @method('PATCH')
        <input type="hidden" name="active" value="{{ $role->is_active ? 0 : 1 }}">
        @if ($role->is_active)
            <input type="hidden" name="confirm" value="0">
            <noscript>
                <div class="form-check text-start mb-2">
                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-off-{{ $role->uuid }}">
                    <label class="form-check-label" for="confirm-off-{{ $role->uuid }}">
                        {{ __('Yes: people with this role won\'t be able to do anything with it') }}
                    </label>
                </div>
            </noscript>
            <button type="submit" class="btn btn-sm btn-soft-warning" title="{{ __('Switch off') }}">
                <i class="bx bx-power-off"></i> {{ $label ?? '' }}
            </button>
        @else
            <button type="submit" class="btn btn-sm btn-soft-success" title="{{ __('Switch on') }}">
                <i class="bx bx-power-off"></i> {{ $label ?? '' }}
            </button>
        @endif
    </form>
    @include('partials.swal-confirm-script')
@endcan
