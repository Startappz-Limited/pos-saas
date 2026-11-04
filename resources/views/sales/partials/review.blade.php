@if (! empty($sale->sync_flags))
    {{-- How a held till sale was accepted (stage 10, step 5c). --}}
    <div class="alert alert-info" role="status" id="sale-sync-flags">
        @if (! empty($sale->sync_flags['rerung']['invoice_number']))
            <p class="mb-1">{{ __('Re-rung from till receipt :number: the till\'s number was already taken, so this sale was recorded on the server with a web number.', ['number' => $sale->sync_flags['rerung']['invoice_number']]) }}</p>
        @endif
        @php
            $labels = [
                'accepted_outside_lease' => __('accepted outside the till\'s trading lease'),
                'accepted_invalid_series' => __('accepted with a number outside the till\'s series'),
                'large_line' => __('a line above the usual limits'),
                'large_amount' => __('an amount above the usual limits'),
                'bad_email' => __('the customer\'s email left out, as it wasn\'t an email'),
                'text_shortened' => __('long text shortened'),
            ];
            $shown = collect($sale->sync_flags)->keys()->intersect(array_keys($labels));
        @endphp
        @if ($shown->isNotEmpty())
            <p class="mb-0">{{ __('Accepted from the till as recorded: :flags.', ['flags' => $shown->map(fn ($flag) => $labels[$flag])->implode('; ')]) }}</p>
        @endif
    </div>
@endif
{{--
    A sale flagged for review (stage 10, step 5b): an online order cancelled after
    dispatch, unrefunded or part-refunded in its store. Shown until resolved; the owner
    can record a refund owed for a voided online sale the store didn't refund.
--}}
@php
    $reviews = \App\Models\Alert::query()->where('alertable_type', \App\Models\Sale::class)->where('alertable_id', $sale->id)
        ->where('type', \App\Enums\AlertType::SALE_REVIEW->value)->where('is_resolved', false)->latest('id')->get();
    $storeRefunded = \App\Models\RefundDue::query()->where('sale_id', $sale->id)->where('resolution', 'refunded_in_store')->exists();
@endphp
@foreach ($reviews as $review)
    <div class="alert alert-warning" role="status" id="sale-review-{{ $review->id }}">
        <h6 class="alert-heading mb-1">{{ __('Needs review') }}</h6>
        <p class="mb-2">{{ $review->message }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            @can('update', $review)
                <form action="{{ route('alerts.resolve', $review) }}" method="POST" class="d-flex flex-wrap gap-2">
                    @csrf
                    <label for="review-notes-{{ $review->id }}" class="visually-hidden">{{ __('What you found') }}</label>
                    <input type="text" name="resolution_notes" id="review-notes-{{ $review->id }}" class="form-control form-control-sm" maxlength="1000" placeholder="{{ __('What you found') }}">
                    <button type="submit" class="btn btn-sm btn-light">{{ __('Mark checked') }}</button>
                </form>
            @endcan
        </div>
    </div>
@endforeach
@if ($sale->status === 'voided' && $sale->ecommerceOrder && ! $storeRefunded && auth()->user()->isBusinessOwner())
    <div class="card border-warning mb-3" id="store-refund-owed">
        <div class="card-body">
            <h6 class="mb-2">{{ __('Record a refund owed') }}</h6>
            <p class="text-muted small">{{ __('If the customer paid in the online store and wasn\'t refunded there, record what you owe them; it is listed under Refunds owed for payment.') }}</p>
            <form action="{{ route('sales.refunds-due.store', $sale) }}" method="POST" class="row g-2 align-items-end">
                @csrf
                @honeypot
                <div class="col-sm-3">
                    <label for="refund-amount" class="form-label">{{ __('Amount') }}</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="refund-amount" class="form-control @error('amount') is-invalid @enderror" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-3">
                    <label for="refund-method" class="form-label">{{ __('Method') }}</label>
                    <select name="method" id="refund-method" class="form-select" required>
                        @foreach (['mobile_money' => __('M-Pesa'), 'bank_transfer' => __('Bank transfer'), 'card' => __('Card'), 'cash' => __('Cash'), 'cheque' => __('Cheque')] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <label for="refund-reference" class="form-label">{{ __('Payment reference') }}</label>
                    <input type="text" name="reference" id="refund-reference" class="form-control" maxlength="255">
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-warning w-100">{{ __('Record') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
