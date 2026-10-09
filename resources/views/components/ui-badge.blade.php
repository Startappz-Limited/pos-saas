{{--
    UI Component: Badge
    
    Usage:
    <x-ui-badge variant="success">Active</x-ui-badge>
    <x-ui-badge variant="danger" pill>Inactive</x-ui-badge>
    
    Props:
    - variant: primary, secondary, success, danger, warning, info, light, dark (default 'primary')
    - pill: Make badge pill-shaped (boolean, default false)
    - soft: Use soft variant (boolean, default false)
    - class: Additional CSS classes
--}}

<span
    class="badge 
    {{ $soft ?? false ? 'bg-' . ($variant ?? 'primary') . '-subtle text-' . ($variant ?? 'primary') : 'bg-' . ($variant ?? 'primary') }}
    {{ $pill ?? false ? 'rounded-pill' : '' }}
    {{ $class ?? '' }}"
    {{ $attributes }}>
    {{ $slot }}
</span>
