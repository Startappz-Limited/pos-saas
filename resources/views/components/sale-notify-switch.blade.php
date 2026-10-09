@props([
    'shop',
    'channel',
    'enabled' => false,
    'disabled' => false,
    'label' => '',
])

@php
    $switchId = 'sale-notify-' . $channel . '-' . $shop->id;
@endphp

{{-- Self-submitting switch: the hidden field carries the flipped value, so the
     checkbox itself needs no name and one POST toggles exactly one channel. --}}
<form method="POST" action="{{ route('baileys.automation.saleNotifications', $shop) }}"
    class="form-check form-switch d-flex justify-content-center mb-0">
    @csrf
    <input type="hidden" name="channel" value="{{ $channel }}">
    <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
    <input class="form-check-input ms-0" type="checkbox" role="switch" id="{{ $switchId }}" @checked($enabled)
        @disabled($disabled) onchange="this.form.submit()" aria-label="{{ $label }}">
    <label class="visually-hidden" for="{{ $switchId }}">{{ $label }}</label>
</form>
