{{--
    UI Component: Action Buttons
    
    Usage:
    <x-ui-action-buttons 
        :model="$product" 
        route="products"
        :showView="true"
        :showEdit="true"
        :showDelete="true"
    />
    
    Props:
    - model: Eloquent model instance
    - route: Route prefix (e.g., 'products', 'users')
    - showView: Show view button (boolean, default true)
    - showEdit: Show edit button (boolean, default true)
    - showDelete: Show delete button (boolean, default true)
    - viewPermission: Permission name for view (optional)
    - editPermission: Permission name for edit (optional)
    - deletePermission: Permission name for delete (optional)

    Without a permission name each button asks the model's policy (view /
    update / delete), which also applies its shop, business and owner rules.
    (The defaults used to be "{route}.show/.edit/.destroy": route names, not
    permissions, so only a super-admin ever saw the buttons.)
--}}

<div class="d-flex gap-2">
    @if ($showView ?? true)
        @can($viewPermission ?? 'view', $model)
            <a href="{{ route("{$route}.show", $model) }}" class="btn btn-light btn-sm" title="View">
                <iconify-icon icon="solar:eye-broken" class="align-middle fs-18"></iconify-icon>
            </a>
        @endcan
    @endif

    @if ($showEdit ?? true)
        @can($editPermission ?? 'update', $model)
            <a href="{{ route("{$route}.edit", $model) }}" class="btn btn-soft-primary btn-sm" title="Edit">
                <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
            </a>
        @endcan
    @endif

    @if ($showDelete ?? true)
        @can($deletePermission ?? 'delete', $model)
            <form action="{{ route("{$route}.destroy", $model) }}" method="POST" class="d-inline"
                onsubmit="return confirm('Are you sure you want to delete this item?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-soft-danger btn-sm" title="Delete">
                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken" class="align-middle fs-18"></iconify-icon>
                </button>
            </form>
        @endcan
    @endif
</div>
