{{--
    UI Component: Loading Spinner
    
    Usage:
    <x-ui-loading text="Loading products..." />
    <x-ui-loading size="sm" variant="primary" />
    
    Props:
    - size: sm, lg (optional, default normal)
    - variant: primary, secondary, etc. (default 'primary')
    - text: Loading text (optional)
--}}

<div class="text-center {{ $class ?? '' }}">
    <div class="spinner-border text-{{ $variant ?? 'primary' }} 
        {{ isset($size) ? 'spinner-border-' . $size : '' }}"
        role="status">
        <span class="visually-hidden">Loading...</span>
    </div>

    @if (isset($text))
        <p class="mt-2 text-muted">{{ $text }}</p>
    @endif
</div>
