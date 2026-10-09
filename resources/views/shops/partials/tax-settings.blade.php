{{--
    VAT registration and pricing convention for a shop.

    `vat_registered` is the master gate on the whole VAT engine: while it is off
    the till stores whatever tax figure it is given and charges none of its own,
    which is the correct behaviour for a business below the registration
    threshold. Turning it on makes the server compute VAT from the sale lines.

    Expects: $shop (nullable — the create form passes none).
--}}
@php
    $shop = $shop ?? null;
    $taxSettings = $shop?->taxSettings() ?? [];
    $vatRegistered = (bool) old('vat_registered', $shop?->vat_registered ?? false);
    $pricesIncludeTax = (bool) old(
        'settings.tax.prices_include_tax',
        $taxSettings['prices_include_tax'] ?? config('tax.prices_include_tax', true),
    );
    $defaultClass = old(
        'settings.tax.default_class',
        $taxSettings['default_class'] ?? config('tax.default_class', 'standard'),
    );
    $standardRate = number_format((float) config('tax.rates.standard', 16), 0);
@endphp

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <iconify-icon icon="solar:bill-check-bold-duotone"
                class="align-middle text-success me-2"></iconify-icon>
            {{ __('Tax & Compliance') }}
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="tax_pin" class="form-label">{{ __('KRA PIN') }}</label>
                <input type="text" class="form-control text-uppercase @error('tax_pin') is-invalid @enderror"
                    id="tax_pin" name="tax_pin" maxlength="32" placeholder="P051234567X"
                    value="{{ old('tax_pin', $shop?->tax_pin) }}">
                @error('tax_pin')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('Printed on every invoice and receipt.') }}</div>
            </div>

            <div class="col-md-6">
                <label for="settings_tax_default_class" class="form-label">{{ __('Default tax class') }}</label>
                <select class="form-select @error('settings.tax.default_class') is-invalid @enderror"
                    id="settings_tax_default_class" name="settings[tax][default_class]">
                    @foreach (\App\Enums\TaxClass::options() as $value => $label)
                        <option value="{{ $value }}" @selected($defaultClass === $value)>
                            {{ $label }}
                            @if ($value === 'standard')
                                ({{ $standardRate }}%)
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('settings.tax.default_class')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    {{ __('Applied to any product that does not set its own tax class.') }}
                </div>
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="vat_registered" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="vat_registered"
                        name="vat_registered" value="1" @checked($vatRegistered)>
                    <label class="form-check-label" for="vat_registered">
                        {{ __('This shop is registered for VAT') }}
                    </label>
                </div>
                <div class="form-text">
                    {{ __('While this is off, no VAT is calculated or charged. Turn it on only once the business is actually registered.') }}
                </div>
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="settings[tax][prices_include_tax]" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="settings_tax_prices_include_tax"
                        name="settings[tax][prices_include_tax]" value="1" @checked($pricesIncludeTax)>
                    <label class="form-check-label" for="settings_tax_prices_include_tax">
                        {{ __('Selling prices already include VAT') }}
                    </label>
                </div>
                <div class="form-text">
                    {{ __('On (retail): the ticket price is what the customer pays and VAT is extracted from it. Off (wholesale): prices are net and VAT is added at the till.') }}
                </div>
            </div>

            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <iconify-icon icon="solar:info-circle-bold" class="align-middle me-1"></iconify-icon>
                    <small>
                        {{ __('Set each product\'s tax class on the product form. Zero-rated and exempt both charge no VAT, but they are reported separately on the VAT return.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
