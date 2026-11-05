{{--
    Voiding on the sale page (stage 10, step 5a): the line under the Void button (until
    when) or instead of it (why not), the "Voided" panel, and the confirmation window.
    $voiding comes from App\Support\Sales\VoidButton; null for a voided or deleted sale.
--}}
@if ($sale->status === 'voided')
    <div class="alert alert-danger" role="status" id="sale-voided">
        <span class="badge bg-danger me-1">{{ __('Voided') }}</span>
        {{ __(':when by :name. Reason: :reason', [
            'when' => $sale->voided_at?->format('M d, Y H:i') ?? '—',
            'name' => $sale->voider?->name ?? __('the system'),
            'reason' => $sale->void_reason ?? '—',
        ]) }}
    </div>
@elseif ($voiding !== null && $voiding['show'] && $voiding['until'])
    <p class="text-muted small mb-3" id="void-until">{{ $voiding['until'] }}</p>
@elseif ($voiding !== null && $voiding['reason'])
    <p class="text-muted small mb-3" id="void-unavailable">{{ $voiding['reason'] }}</p>
@endif

@if ($voiding['show'] ?? false)
    <div class="modal fade" id="voidSaleModal" tabindex="-1" aria-labelledby="voidSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('sales.void', $sale) }}" method="POST" novalidate>
                    @csrf
                    @honeypot
                    <div class="modal-header bg-danger-subtle">
                        <h5 class="modal-title text-danger" id="voidSaleModalLabel">{{ __('Void this sale?') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="mb-3 ps-3">
                            <li>{{ __('The sale is cancelled and no longer counts in sales, cost or profit reports.') }}</li>
                            <li>{{ __('All :count items go back into stock in :shop.', ['count' => $voiding['items'], 'shop' => $voiding['shop']]) }}</li>
                            <li>{{ __('If it was a credit sale, the customer no longer owes for it.') }}</li>
                            @foreach ($voiding['money'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                            <li>{{ __('The invoice number :invoice stays in the records, marked Voided.', ['invoice' => $voiding['invoice']]) }}</li>
                            <li>{{ __('This can\'t be undone.') }}</li>
                        </ul>
                        <p class="text-muted small">
                            {{ __('Use Void when the sale was a mistake (wrong item, price or quantity, payment that didn\'t go through, a duplicate) and the goods haven\'t left the shop. If the customer took the goods and brought them back, use a return instead.') }}
                        </p>
                        <label for="void_reason" class="form-label">{{ __('Reason') }} <span class="text-danger">*</span></label>
                        <textarea name="void_reason" id="void_reason" rows="2" maxlength="255" required
                            class="form-control @error('void_reason') is-invalid @enderror"
                            @error('void_reason') aria-describedby="void_reason-error" @enderror>{{ old('void_reason') }}</textarea>
                        @error('void_reason')
                            <div class="invalid-feedback" id="void_reason-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Void sale') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('voidSaleModal');
        modal.addEventListener('shown.bs.modal', () => document.getElementById('void_reason').focus());
        @if ($errors->has('void_reason'))
            // Back with an error: reopen the window, with focus on the field.
            new bootstrap.Modal(modal).show();
        @endif
    });
    </script>
    @endpush
@endif
