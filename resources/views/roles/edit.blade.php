@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    @include('partials.swal-confirm-script')
    <div class="row">
        <div class="col-xl-10 col-lg-11 mx-auto">
            <form action="{{ route('roles.update', $role) }}" method="POST"
                @if ($role->is_active)
                    data-swal-confirm data-swal-when="is_active"
                    data-swal-title="{{ __('Switch this role off?') }}"
                    data-swal-text="{{ __('People with this role won\'t be able to do anything in the system with it until it\'s switched back on.') }} {{ trans_choice('{0} Nobody in this business holds it.|{1} :count person in this business holds it.|[2,*] :count people in this business hold it.', $role->holders_count, ['count' => $role->holders_count]) }}"
                    data-swal-button="{{ __('Save and switch off') }}"
                @endif>
                @csrf
                @method('PUT')
                @if ($role->is_active)
                    <input type="hidden" name="confirm" value="0">
                @endif

                <!-- Role Information -->
                <x-ui-card title="Role Information" class="mb-3">
                    @if ($role->isTemplateCopy())
                        <x-ui-alert variant="warning" class="mb-3">
                            <i class="bx bx-error me-1"></i>
                            {{ __('This is a built-in role: it keeps its name and can\'t be deleted. You can change its permissions, or reset it to default.') }}
                        </x-ui-alert>
                    @endif

                    <div class="row">
                        <div class="col-lg-6">
                            <x-ui-form-input name="name" label="Role Name" :value="old('name', $role->name)"
                                placeholder="e.g. night-shift" required :readonly="$role->isTemplateCopy()"
                                :help="$role->isTemplateCopy() ? __('Shown as :name.', ['name' => $role->displayName()]) : __('Lowercase letters, numbers and hyphens.')" />
                        </div>


                        <div class="col-lg-6">
                            <div class="form-check form-switch mb-3">
                                {{-- An unticked box sends nothing: this says "off". --}}
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                    value="1" {{ old('is_active', $role->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    {{ __('Active') }}
                                </label>
                                @if ($role->is_active)
                                    <noscript>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-off-edit">
                                            <label class="form-check-label" for="confirm-off-edit">
                                                {{ __('If unticked: yes, people with this role won\'t be able to do anything with it') }}
                                            </label>
                                        </div>
                                    </noscript>
                                @endif
                                @error('confirm')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </x-ui-card>

                <!-- Permissions -->
                <x-ui-card title="Assign Permissions" class="mb-3">
                    <x-ui-alert variant="info" class="mb-3">
                        <i class="bx bx-info-circle me-1"></i>
                        Select the permissions this role should have. Users with this role will inherit all selected
                        permissions.
                    </x-ui-alert>

                    @if ($outOfReach > 0)
                        <x-ui-alert variant="secondary" class="mb-3">
                            {{ trans_choice('{1} This role has :count more permission that you don\'t hold. It stays as it is.|[2,*] This role has :count more permissions that you don\'t hold. They stay as they are.', $outOfReach, ['count' => $outOfReach]) }}
                        </x-ui-alert>
                    @endif
                    @unless (auth()->user()->isBusinessOwner())
                        <p class="text-muted small mb-3">{{ __('Only the permissions you hold are listed: you can give a role no more than you have.') }}</p>
                    @endunless
                    <x-ui-permission-grid :permissions="$permissions" :selectedPermissions="old('permissions', $role->permissions->pluck('id')->toArray())" groupBy="module" />
                </x-ui-card>

                <!-- Role Statistics -->
                <x-ui-card title="Role Statistics" class="mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-1">{{ $role->holders_count }}</h3>
                                <p class="text-muted mb-0">Users Assigned</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-1">{{ $role->permissions->count() }}</h3>
                                <p class="text-muted mb-0">Permissions</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-1">{{ $role->created_at->diffForHumans() }}</h3>
                                <p class="text-muted mb-0">Created</p>
                            </div>
                        </div>
                    </div>
                </x-ui-card>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <x-ui-button variant="primary" type="submit" icon="solar:check-circle-broken">
                        Update Role
                    </x-ui-button>
                    <x-ui-button variant="secondary" href="{{ route('roles.index') }}">
                        Cancel
                    </x-ui-button>
                    @can('delete', $role)
                        <x-ui-button variant="danger" data-bs-toggle="modal" data-bs-target="#deleteRoleModal"
                            class="float-end">
                            Delete Role
                        </x-ui-button>
                    @endcan
                </div>
            </form>
        </div>
    </div>

        @can('delete', $role)
            @push('modals')
                <x-ui-modal id="deleteRoleModal" title="Confirm Delete" centered>
                    <x-slot:body>
                        <p class="mb-3">Are you sure you want to delete the <strong>{{ $role->displayName() }}</strong> role?</p>
                        @if ($role->holders_count > 0)
                            <x-ui-alert variant="warning">
                                <strong>Warning:</strong> This role is assigned to {{ $role->holders_count }} user(s). Deleting
                                it will remove this role from all assigned users.
                            </x-ui-alert>
                        @endif
                    </x-slot:body>

                    <x-slot:footer>
                        <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
                        <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <x-ui-button variant="danger" type="submit">
                                Delete Role
                            </x-ui-button>
                        </form>
                    </x-slot:footer>
                </x-ui-modal>
            @endpush
        @endcan
@endsection
