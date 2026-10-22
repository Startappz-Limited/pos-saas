{{-- The business's roles, and resetting a copy on its behalf (Phase 5, B6). --}}
<x-ui-card :title="__('Roles')">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Kind') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Holders') }}</th>
                    <th class="text-end">{{ __('Permissions') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>{{ $role->displayName() }}</td>
                        <td>{{ $role->isTemplateCopy() ? __('Built-in') : __('Custom') }}</td>
                        <td>{{ $role->is_active ? __('Active') : __('Inactive') }}</td>
                        <td class="text-end">{{ $role->holders_count }}</td>
                        <td class="text-end">{{ $role->permissions_count }}</td>
                        <td class="text-end">
                            @if ($role->isTemplateCopy())
                                <form method="POST" action="{{ route('platform.businesses.roles.reset', [$business, $role->uuid]) }}" class="d-inline"
                                    data-swal-confirm data-swal-title="{{ __('Reset :role to default?', ['role' => $role->displayName()]) }}"
                                    data-swal-text="{{ __('Its permissions in :business are replaced by the template\'s current ones.', ['business' => $business->name]) }}"
                                    data-swal-button="{{ __('Reset to default') }}">
                                    @csrf
                                    <input type="hidden" name="confirm" value="0">
                                    <noscript>
                                        <input class="form-check-input" type="checkbox" name="confirm" value="1" aria-label="{{ __('Confirm the reset') }}">
                                    </noscript>
                                    <button type="submit" class="btn btn-sm btn-soft-warning">{{ __('Reset to default') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-ui-card>
