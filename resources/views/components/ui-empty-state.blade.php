{{--
    UI Component: Empty State
    
    Usage:
    <x-ui-empty-state 
        icon="solar:box-broken"
        title="No products found"
        message="Get started by adding your first product"
        actionText="Add Product"
        actionUrl="{{ route('products.create') }}"
    />
    
    Props:
    - icon: Iconify icon name
    - title: Main heading
    - message: Description text (optional)
    - actionText: Button text (optional)
    - actionUrl: Button URL (optional)
--}}

<div class="text-center py-5">
    <div class="avatar-xl mx-auto mb-4">
        <div class="avatar-title bg-soft-primary text-primary rounded-circle fs-1">
            @if (isset($icon))
                <iconify-icon icon="{{ $icon }}"></iconify-icon>
            @else
                <i class="bx bx-inbox"></i>
            @endif
        </div>
    </div>

    <h4 class="mb-2">{{ $title }}</h4>

    @if (isset($message))
        <p class="text-muted mb-4">{{ $message }}</p>
    @endif

    @if (isset($actionText) && isset($actionUrl))
        <a href="{{ $actionUrl }}" class="btn btn-primary">
            @if (isset($actionIcon))
                <iconify-icon icon="{{ $actionIcon }}" class="align-middle me-1"></iconify-icon>
            @endif
            {{ $actionText }}
        </a>
    @endif

    {{ $slot }}
</div>
