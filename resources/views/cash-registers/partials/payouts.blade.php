{{--
    A session's payouts (stage 10, step 4): void refunds, refunds owed paid later and
    petty cash, each lowering its expected cash. Someone holding cash-registers.payout
    records petty cash on an open session, with a reason.
--}}
<div class="mt-4" id="session-payouts">
    <h6 class="text-muted text-uppercase fw-semibold mb-3">{{ __('Payouts') }}</h6>
    @if ($cashRegister->payouts->isNotEmpty())
        <div class="table-responsive">
            <table class="table align-middle mb-3">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Sale') }}</th>
                        <th>{{ __('By') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cashRegister->payouts as $payout)
                        <tr>
                            <td>{{ $payout->created_at?->format('M d, Y H:i') }}</td>
                            <td>{{ $payout->reason }}</td>
                            <td>
                                @if ($payout->sale)
                                    <a href="{{ route('sales.show', $payout->sale) }}">{{ $payout->sale->invoice_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $payout->creator?->name ?? '—' }}</td>
                            <td class="text-end text-danger">{{ format_currency($payout->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-muted">{{ __('No cash has been paid out of this session.') }}</p>
    @endif

    @can('cash-payouts.create', $cashRegister)
        <form action="{{ route('cash-registers.payouts.store', $cashRegister) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            @honeypot
            <div class="col-sm-3">
                <label for="payout-amount" class="form-label">{{ __('Amount') }} <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" id="payout-amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label for="payout-reason" class="form-label">{{ __('Reason') }} <span class="text-danger">*</span></label>
                <input type="text" name="reason" id="payout-reason" maxlength="255" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" required>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-outline-danger w-100">{{ __('Record payout') }}</button>
            </div>
        </form>
    @endcan
</div>
