@props([
    'permissions' => [],
    'selectedPermissions' => [],
    'groupBy' => null,
])

@php
    // Group permissions if groupBy is specified
    if ($groupBy === 'module') {
        // Group by the first part of the permission name (e.g., "users" from "users.view")
        $groupedPermissions = collect($permissions)->groupBy(function ($permission) {
            return explode('.', $permission->name ?? $permission['name'])[0];
        });
    } elseif ($groupBy) {
        $groupedPermissions = collect($permissions)->groupBy($groupBy);
    } else {
        $groupedPermissions = ['all' => collect($permissions)];
    }
@endphp

<div class="permissions-grid">
    @foreach ($groupedPermissions as $group => $perms)
        @if ($groupBy)
            <div class="permission-group mb-4">
                <h6 class="text-uppercase fw-semibold text-muted mb-3">{{ \Illuminate\Support\Str::ucfirst(str_replace(['-', '_'], ' ', $group)) }}</h6>
        @endif

        <div class="row g-3">
            @foreach ($perms as $permission)
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]"
                            value="{{ $permission->id ?? $permission['id'] }}"
                            id="permission_{{ $permission->id ?? $permission['id'] }}"
                            {{ in_array($permission->id ?? $permission['id'], $selectedPermissions) ? 'checked' : '' }}>
                        @php
                            $permissionName = $permission->name ?? $permission['name'];
                            $action = str_contains($permissionName, '.') ? \Illuminate\Support\Str::after($permissionName, '.') : $permissionName;
                        @endphp
                        <label class="form-check-label" for="permission_{{ $permission->id ?? $permission['id'] }}" title="{{ $permissionName }}">
                            {{ \Illuminate\Support\Str::ucfirst(str_replace(['-', '_'], ' ', $action)) }}
                        </label>
                        @if ($action === 'full-access')
                            {{-- Phase 5: explained where it's chosen. --}}
                            <div class="form-text">{{ __('Counts as every permission in this area.') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($groupBy)
</div>
@endif
@endforeach
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select All functionality for each group
            document.querySelectorAll('.permission-group').forEach(group => {
                const checkboxes = group.querySelectorAll('input[type="checkbox"]');

                // Add select all button
                const heading = group.querySelector('h6');
                if (heading && checkboxes.length > 0) {
                    const selectAllBtn = document.createElement('button');
                    selectAllBtn.type = 'button';
                    selectAllBtn.className = 'btn btn-sm btn-link text-primary p-0 ms-2';
                    selectAllBtn.textContent = 'Select All';
                    selectAllBtn.onclick = function() {
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        checkboxes.forEach(cb => cb.checked = !allChecked);
                        this.textContent = allChecked ? 'Select All' : 'Deselect All';
                    };
                    heading.appendChild(selectAllBtn);
                }
            });
        });
    </script>
@endpush
