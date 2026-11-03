{{-- An adjustment's direction, derived from its lines (stage 10, step 1g). --}}
@php
    $typeColor = match ($type) {
        App\Enums\AdjustmentType::INCREASE => 'success',
        App\Enums\AdjustmentType::DECREASE => 'danger',
        default => 'info',
    };
    $typeIcon = match ($type) {
        App\Enums\AdjustmentType::INCREASE => 'solar:arrow-up-bold',
        App\Enums\AdjustmentType::DECREASE => 'solar:arrow-down-bold',
        default => 'solar:sort-vertical-bold',
    };
@endphp
<span class="badge bg-{{ $typeColor }}-subtle text-{{ $typeColor }}">
    <iconify-icon icon="{{ $typeIcon }}" class="align-middle" aria-hidden="true"></iconify-icon>
    {{ __($type->label()) }}
</span>
