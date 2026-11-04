{{--
    A held till item's resolutions (stage 10, step 5c): accept (with the corrections the
    review offers, or as recorded), re-ring a sale whose number was taken as a new web sale,
    or discard with a reason. $options comes from App\Support\Sync\HeldItems::options().
--}}
@php
    $item = (array) ($issue->details['item'] ?? []);
    $mayDiscard = app(\App\Support\Sync\HeldItems::class)->mayDiscard(auth()->user(), $issue);
@endphp
<div class="d-flex flex-column gap-2 align-items-end">
    @can('sync-issues.accept')
        @if ($options['corrections'] !== [])
            <form method="POST" action="{{ route('sync-issues.accept', $issue) }}" class="text-start border rounded p-2 w-100">
                @csrf
                @honeypot
                <p class="small text-muted mb-2">{{ __('Correct what the till sent:') }}</p>
                @if (in_array('customer', $options['corrections'], true))
                    <label class="form-label small" for="held-customer-{{ $issue->id }}">{{ __('Customer') }}</label>
                    <select name="customer_id" id="held-customer-{{ $issue->id }}" class="form-select form-select-sm mb-2">
                        <option value="">{{ __('Walk-in (no customer)') }}</option>
                        @foreach ($lists['customers'] ?? [] as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} {{ $customer->phone ? '('.$customer->phone.')' : '' }} — {{ $customer->customer_type }}</option>
                        @endforeach
                    </select>
                @endif
                @if (in_array('walk_in', $options['corrections'], true) || in_array('customer', $options['corrections'], true))
                    <div class="row g-2 mb-2">
                        <div class="col"><label class="form-label small" for="held-walkin-name-{{ $issue->id }}">{{ __('Walk-in name') }}</label>
                            <input type="text" name="walk_in_customer_name" id="held-walkin-name-{{ $issue->id }}" class="form-control form-control-sm" value="{{ $item['walk_in_customer_name'] ?? '' }}"></div>
                        <div class="col"><label class="form-label small" for="held-walkin-phone-{{ $issue->id }}">{{ __('Walk-in phone') }}</label>
                            <input type="text" name="walk_in_customer_phone" id="held-walkin-phone-{{ $issue->id }}" class="form-control form-control-sm" value="{{ $item['walk_in_customer_phone'] ?? '' }}"></div>
                    </div>
                @endif
                @if (in_array('payment_method', $options['corrections'], true))
                    <label class="form-label small" for="held-method-{{ $issue->id }}">{{ __('Payment method') }}</label>
                    <select name="payment_method" id="held-method-{{ $issue->id }}" class="form-select form-select-sm mb-2">
                        @foreach (['cash' => __('Cash'), 'card' => __('Card'), 'mobile_money' => __('M-Pesa'), 'bank_transfer' => __('Bank transfer'), 'credit' => __('Credit')] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                @if (in_array('source', $options['corrections'], true))
                    <label class="form-label small" for="held-source-{{ $issue->id }}">{{ __('Sale source') }}</label>
                    <select name="source_id" id="held-source-{{ $issue->id }}" class="form-select form-select-sm mb-2">
                        @foreach ($lists['sources'] ?? [] as $source)
                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                        @endforeach
                    </select>
                @endif
                @if (in_array('delivery_location', $options['corrections'], true))
                    <label class="form-label small" for="held-location-{{ $issue->id }}">{{ __('Delivery location') }}</label>
                    <input type="text" name="delivery_location" id="held-location-{{ $issue->id }}" class="form-control form-control-sm mb-2" value="{{ $item['delivery_location'] ?? __('In shop') }}">
                @endif
                @if (in_array('delivery_company', $options['corrections'], true))
                    <label class="form-label small" for="held-company-{{ $issue->id }}">{{ __('Delivery company') }}</label>
                    <select name="delivery_company_id" id="held-company-{{ $issue->id }}" class="form-select form-select-sm mb-2">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($lists['deliveryCompanies'] ?? [] as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                @endif
                @if (in_array('tax_pin', $options['corrections'], true))
                    <label class="form-label small" for="held-pin-{{ $issue->id }}">{{ __('Customer KRA PIN (leave empty to clear)') }}</label>
                    <input type="text" name="customer_tax_pin" id="held-pin-{{ $issue->id }}" class="form-control form-control-sm mb-2" value="">
                @endif
                @if (in_array('variation', $options['corrections'], true))
                    @foreach ((array) ($item['items'] ?? []) as $index => $line)
                        @php($variations = \App\Models\ProductVariation::query()->where('product_id', $line['product_id'] ?? 0)->where('is_default', false)->get(['id', 'name']))
                        @if ($variations->isNotEmpty())
                            <label class="form-label small" for="held-variation-{{ $issue->id }}-{{ $index }}">{{ __('Variation for line :n', ['n' => $index + 1]) }}</label>
                            <select name="variations[{{ $index }}]" id="held-variation-{{ $issue->id }}-{{ $index }}" class="form-select form-select-sm mb-2">
                                @foreach ($variations as $variation)
                                    <option value="{{ $variation->id }}">{{ $variation->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    @endforeach
                @endif
                @if (in_array('cashier', $options['corrections'], true))
                    <label class="form-label small" for="held-cashier-{{ $issue->id }}">{{ __('Cashier') }}</label>
                    <select name="cashier" id="held-cashier-{{ $issue->id }}" class="form-select form-select-sm mb-2">
                        @foreach ($lists['staff'] ?? [] as $person)
                            <option value="{{ $person->uuid }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                @endif
                <x-ui-button variant="primary" size="sm" type="submit">{{ __('Correct and accept') }}</x-ui-button>
            </form>
        @elseif ($options['accept'] || $options['as_recorded'])
            <form method="POST" action="{{ route('sync-issues.accept', $issue) }}">
                @csrf
                @honeypot
                <x-ui-button variant="primary" size="sm" type="submit">{{ $options['as_recorded'] ? __('Accept as recorded') : __('Accept') }}</x-ui-button>
            </form>
        @endif
        @if ($options['rerung'])
            <form method="POST" action="{{ route('sync-issues.rerung', $issue) }}"
                onsubmit="return confirm(@js(__('Record this sale as a new web sale? It gets a web-series number; the till\'s receipt number stays on it as "re-rung from".')))">
                @csrf
                @honeypot
                <x-ui-button variant="outline-primary" size="sm" type="submit">{{ __('Discard and record as a new web sale') }}</x-ui-button>
            </form>
        @endif
    @endcan
    @if ($mayDiscard)
        <form method="POST" action="{{ route('sync-issues.discard', $issue) }}" class="d-flex gap-2">
            @csrf
            @honeypot
            <label for="held-reason-{{ $issue->id }}" class="visually-hidden">{{ __('Why discard it?') }}</label>
            <input type="text" name="reason" id="held-reason-{{ $issue->id }}" class="form-control form-control-sm" maxlength="500" required placeholder="{{ __('Why discard it?') }}">
            <x-ui-button variant="outline-danger" size="sm" type="submit">{{ __('Discard') }}</x-ui-button>
        </form>
    @endif
</div>
