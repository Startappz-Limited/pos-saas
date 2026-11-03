{{--
    Void requests on the sale page (stage 10, step 2): someone without sales.void asks
    here, with a reason; while one is pending it shows who asked and why, and an
    approver approves (which voids the sale) or rejects it with a reason.
--}}
@php
    $voidRequest = \App\Models\SaleVoidRequest::query()->pending()->where('sale_id', $sale->id)->with('requester:id,name')->latest('id')->first();
@endphp
@if ($voidRequest)
    <div class="alert alert-warning mt-4" role="status" id="void-request">
        <h6 class="alert-heading mb-1">{{ __('Void requested') }}</h6>
        <p class="mb-2">
            {{ __(':name asked :when: :reason', [
                'name' => $voidRequest->requester?->name ?? __('a former member'),
                'when' => $voidRequest->requested_at?->diffForHumans(),
                'reason' => $voidRequest->reason,
            ]) }}
            <br><small class="text-muted">{{ __('It can be approved until :at.', ['at' => $voidRequest->expires_at?->format('M d, Y H:i')]) }}</small>
        </p>
        <div class="d-flex flex-wrap gap-2">
            @can('approve', $voidRequest)
                <form action="{{ route('void-requests.approve', $voidRequest) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(@js(__('Void this sale?')))">{{ __('Approve and void') }}</button>
                </form>
            @endcan
            @can('reject', $voidRequest)
                <form action="{{ route('void-requests.reject', $voidRequest) }}" method="POST" class="d-flex flex-wrap gap-2">
                    @csrf
                    <label for="void-reject-reason" class="visually-hidden">{{ __('Why reject it?') }}</label>
                    <input type="text" name="rejection_reason" id="void-reject-reason" class="form-control form-control-sm" maxlength="500" required placeholder="{{ __('Why reject it?') }}">
                    <button type="submit" class="btn btn-light btn-sm">{{ __('Reject') }}</button>
                </form>
            @endcan
            @can('cancel', $voidRequest)
                <form action="{{ route('void-requests.cancel', $voidRequest) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-light btn-sm">{{ __('Cancel request') }}</button>
                </form>
            @endcan
        </div>
    </div>
@elseif (auth()->user()->can('requestVoid', $sale))
    <div class="mt-4" id="request-void">
        <h6 class="text-muted text-uppercase fw-semibold mb-2">{{ __('Request a void') }}</h6>
        <p class="text-muted small mb-2">{{ __('You can\'t void sales yourself. Ask someone who can: they are notified, and approving voids the sale.') }}</p>
        <form action="{{ route('sales.void-requests.store', $sale) }}" method="POST" class="d-flex flex-wrap gap-2">
            @csrf
            @honeypot
            <label for="void-request-reason" class="visually-hidden">{{ __('Why should it be voided?') }}</label>
            <input type="text" name="reason" id="void-request-reason" class="form-control" maxlength="255" required placeholder="{{ __('Why should it be voided?') }}">
            <button type="submit" class="btn btn-outline-danger">{{ __('Request void') }}</button>
        </form>
    </div>
@endif
