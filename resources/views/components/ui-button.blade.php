{{--
    UI Component: Button
    
    Usage:
    <x-ui-button variant="primary" icon="plus">Add New</x-ui-button>
    <x-ui-button variant="danger" size="sm" type="submit">Save</x-ui-button>
    
    Props:
    - variant: primary, secondary, success, danger, warning, info, light, dark, outline-* (default 'primary')
    - size: sm, lg (optional)
    - type: button, submit, reset (default 'button')
    - icon: Iconify icon name (optional)
    - iconPosition: left, right (default 'left')
    - class: Additional CSS classes
    - disabled: Disable button (boolean)
--}}

<button type="{{ $type ?? 'button' }}"
    class="btn btn-{{ $variant ?? 'primary' }} @if (isset($size)) btn-{{ $size }} @endif {{ $class ?? '' }}"
    @if ($disabled ?? false) disabled @endif {{ $attributes }}>
    @if (isset($icon) && ($iconPosition ?? 'left') === 'left')
        <iconify-icon icon="{{ $icon }}" class="align-middle me-1"></iconify-icon>
    @endif

    {{ $slot }}

    @if (isset($icon) && ($iconPosition ?? 'left') === 'right')
        <iconify-icon icon="{{ $icon }}" class="align-middle ms-1"></iconify-icon>
    @endif
</button>
