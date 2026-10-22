{{-- Payments received by hand, until M-Pesa go-live (Phase 5, B2; decision 4.6). --}}
<x-ui-card :title="__('Payments')">
    <form method="POST" action="{{ route('platform.businesses.payments.store', $business) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-3">
            <label class="form-label" for="purpose">{{ __('For') }}</label>
            <select name="purpose" id="purpose" class="form-select form-select-sm @error('purpose') is-invalid @enderror">
                <option value="subscription" @selected(old('purpose') === 'subscription')>{{ __('Subscription') }}</option>
                <option value="ai_topup" @selected(old('purpose') === 'ai_topup')>{{ __('AI top-up (from KES :min)', ['min' => $minTopUp]) }}</option>
            </select>
            @error('purpose')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="amount">{{ __('Amount (KES)') }}</label>
            <input type="number" step="0.01" min="1" name="amount" id="amount" value="{{ old('amount') }}" required
                class="form-control form-control-sm @error('amount') is-invalid @enderror">
            @error('amount')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="receipt">{{ __('M-Pesa receipt') }}</label>
            <input type="text" name="receipt" id="receipt" value="{{ old('receipt') }}" maxlength="30"
                class="form-control form-control-sm text-uppercase @error('receipt') is-invalid @enderror">
            @error('receipt')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="notes">{{ __('Note') }}</label>
            <input type="text" name="notes" id="notes" value="{{ old('notes') }}" maxlength="500"
                class="form-control form-control-sm @error('notes') is-invalid @enderror">
            @error('notes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Record and apply') }}</button>
            <span class="form-text ms-2">{{ __('Recorded in your name. A subscription payment extends the paid period by whole months at the locked price.') }}</span>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('For') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Method') }}</th>
                    <th>{{ __('Receipt') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Recorded by') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $payment->purpose === 'ai_topup' ? __('AI top-up') : __('Subscription') }}</td>
                        <td class="text-end">{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($payment->method) }}</td>
                        <td>{{ $payment->mpesa_receipt ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($payment->status) }}</td>
                        <td>{{ $payment->recorder?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">{{ __('No payments yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui-card>
