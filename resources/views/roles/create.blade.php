@extends('layouts.app')

@section('title', 'Create Role')

@section('content')
    <div class="row">
        <div class="col-xl-10 col-lg-11 mx-auto">
            <form action="{{ route('roles.store') }}" method="POST">
                @csrf

                <!-- Role Information -->
                <x-ui-card title="Role Information" class="mb-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <x-ui-form-input name="name" label="Role Name" :value="old('name')"
                                placeholder="e.g., Store Manager" required autofocus />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-input name="display_name" label="Display Name" :value="old('display_name')"
                                placeholder="e.g., Store Manager" help="Human-readable name for the role" />
                        </div>

                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3"
                                    placeholder="Describe the role's purpose and responsibilities">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                    value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
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

                    <x-ui-permission-grid :permissions="$permissions" :selectedPermissions="old('permissions', [])" groupBy="module" />
                </x-ui-card>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <x-ui-button variant="primary" type="submit" icon="solar:check-circle-broken">
                        Create Role
                    </x-ui-button>
                    <x-ui-button variant="secondary" href="{{ route('roles.index') }}">
                        Cancel
                    </x-ui-button>
                </div>
            </form>
        </div>
    </div>
@endsection
