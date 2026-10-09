{{--
    UI Component: Alert
    
    Usage:
    <x-ui-alert variant="success" dismissible>
        Operation completed successfully!
    </x-ui-alert>
    
    Props:
    - variant: primary, secondary, success, danger, warning, info, light, dark (default 'info')
    - dismissible: Show close button (boolean, default false)
    - icon: Iconify icon name (optional)
    - class: Additional CSS classes
--}}

<div class="alert alert-{{ $variant ?? 'info' }} 
    {{ $dismissible ?? false ? 'alert-dismissible fade show' : '' }}
    {{ $class ?? '' }}"
    role="alert" {{ $attributes }}>
    @if (isset($icon))
        <iconify-icon icon="{{ $icon }}" class="align-middle me-2"></iconify-icon>
    @endif

    {{ $slot }}

    @if ($dismissible ?? false)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
