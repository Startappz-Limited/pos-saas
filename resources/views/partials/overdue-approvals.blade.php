{{--
    Requests waiting over 24 hours for someone to approve them (stage 10): the
    owner sees them until they are settled. Their approvers were reminded once.
--}}
@php
    $overdueAdjustments = \App\Models\StockAdjustment::query()->pending()
        ->where('created_at', '<=', now()->subHours(\App\Actions\Approvals\RemindPendingApprovals::AFTER_HOURS))
        ->with(['shop:id,name', 'creator:id,name'])->orderBy('created_at')->limit(20)->get();
    $overdueVoids = \App\Models\SaleVoidRequest::query()->pending()
        ->where('requested_at', '<=', now()->subHours(\App\Actions\Approvals\RemindPendingApprovals::AFTER_HOURS))
        ->with(['sale:id,uuid,invoice_number', 'shop:id,name', 'requester:id,name'])->orderBy('requested_at')->limit(20)->get();
@endphp
@if ($overdueAdjustments->isNotEmpty() || $overdueVoids->isNotEmpty())
    <div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-start gap-3 mb-3" role="status" id="overdue-approvals">
        <iconify-icon icon="solar:hourglass-bold-duotone" class="fs-1 flex-shrink-0" aria-hidden="true"></iconify-icon>
        <div class="flex-grow-1">
            <h5 class="alert-heading fs-15 mb-1">{{ __('Waiting over 24 hours for approval') }}</h5>
            <ul class="mb-0 ps-3">
                @foreach ($overdueAdjustments as $adjustment)
                    <li>
                        <a href="{{ route('stock-adjustments.show', $adjustment) }}">{{ $adjustment->adjustment_number }}</a>:
                        {{ __(':reason at :shop, by :name, :when', [
                            'reason' => __($adjustment->reason?->label() ?? ''),
                            'shop' => $adjustment->shop?->name,
                            'name' => $adjustment->creator?->name ?? __('a former member'),
                            'when' => $adjustment->created_at?->diffForHumans(),
                        ]) }}
                    </li>
                @endforeach
                @foreach ($overdueVoids as $voidRequest)
                    <li>
                        <a href="{{ route('sales.show', $voidRequest->sale) }}">{{ __('Void sale :invoice', ['invoice' => $voidRequest->sale?->invoice_number]) }}</a>:
                        {{ __(':reason, asked at :shop by :name, :when; it expires :expires', [
                            'reason' => $voidRequest->reason,
                            'shop' => $voidRequest->shop?->name,
                            'name' => $voidRequest->requester?->name ?? __('a former member'),
                            'when' => $voidRequest->requested_at?->diffForHumans(),
                            'expires' => $voidRequest->expires_at?->diffForHumans(),
                        ]) }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
