{{-- The quality status is derived from the quantities, never chosen; the form's script refreshes it as they change. --}}
@php
    $qualityStatus = \App\Enums\StockIntakeQualityStatus::fromQuantities($accepted, $rejected);
    $qualityStatuses = collect(\App\Enums\StockIntakeQualityStatus::cases())
        ->mapWithKeys(fn ($status) => [$status->value => ['label' => $status->label(), 'color' => $status->color()]]);
@endphp
<span class="form-label d-block" id="quality-status-label">{{ __('Quality Status') }}</span>
<div id="quality-status-display" aria-labelledby="quality-status-label" aria-live="polite"
    data-statuses='@json($qualityStatuses)'>
    <span class="badge bg-{{ $qualityStatus->color() }}-subtle text-{{ $qualityStatus->color() }} fs-13"
        data-quality-badge>{{ $qualityStatus->label() }}</span>
</div>
<small class="text-muted">{{ __('Set from the quantities: nothing rejected is Passed, some of each is Partial, nothing accepted is Failed.') }}</small>
