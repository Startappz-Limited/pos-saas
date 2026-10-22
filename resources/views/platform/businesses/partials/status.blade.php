@php
    $variant = match ($status) {
        'active' => 'success',
        'suspended' => 'danger',
        'closing' => 'dark',
        default => 'warning',
    };
@endphp
<x-ui-badge :variant="$variant">{{ \Illuminate\Support\Str::headline($status) }}</x-ui-badge>
