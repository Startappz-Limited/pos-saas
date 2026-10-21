{{--
    UI Component: Button

    Usage:
    <x-ui-button variant="primary" icon="plus">Add New</x-ui-button>
    <x-ui-button variant="danger" size="sm" type="submit">Save</x-ui-button>
    <x-ui-button variant="secondary" href="{{ route('users.index') }}">Cancel</x-ui-button>

    Props:
    - variant: primary, secondary, success, danger, warning, info, light, dark, outline-* (default 'primary')
    - size: sm, lg (optional)
    - type: button, submit, reset (default 'button'; buttons only)
    - href: a URL makes it a link (<a class="btn">) that goes there when clicked
    - icon: Iconify icon name (optional)
    - iconPosition: left, right (default 'left')
    - disabled: Disable the button (boolean); a disabled link can't be followed
    - class and any other attribute (data-bs-*, id, title…) go onto the element.

    A <button> ignores href, so with an href this renders a link: before, every
    button given a link rendered as a button that went nowhere.
--}}

@props([
    'variant' => 'primary',
    'size' => null,
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left',
    'disabled' => false,
])

@php
    $classes = 'btn btn-'.$variant.($size ? ' btn-'.$size : '').($href && $disabled ? ' disabled' : '');
@endphp

@if ($href)
    <a href="{{ $disabled ? '#' : $href }}"
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
        {{ $attributes->merge(['class' => $classes]) }}>
    @else
        <button type="{{ $type }}" @if ($disabled) disabled @endif {{ $attributes->merge(['class' => $classes]) }}>
@endif

@if ($icon && $iconPosition === 'left')
    <iconify-icon icon="{{ $icon }}" class="align-middle me-1"></iconify-icon>
@endif

{{ $slot }}

@if ($icon && $iconPosition === 'right')
    <iconify-icon icon="{{ $icon }}" class="align-middle ms-1"></iconify-icon>
@endif

@if ($href)
    </a>
@else
    </button>
@endif
