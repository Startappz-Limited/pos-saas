@extends('layouts.app')

@section('title', 'Business settings')

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <x-ui-card title="{{ __('Business settings') }}">
                <form method="POST" action="{{ route('business.settings.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <x-ui-form-input name="name" label="{{ __('Business name') }}" value="{{ old('name', $business->name) }}" required />

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="currency">{{ __('Currency') }}</label>
                            <select name="currency" id="currency" class="form-select @error('currency') is-invalid @enderror" required>
                                @foreach ($currencies as $code => $label)
                                    <option value="{{ $code }}" @selected(old('currency', $business->currency) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('Used for every amount, receipt, PDF and report.') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="timezone">{{ __('Timezone') }}</label>
                            <select name="timezone" id="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                                @foreach ($timezones as $zone)
                                    <option value="{{ $zone }}" @selected(old('timezone', $business->timezone) === $zone)>{{ $zone }}</option>
                                @endforeach
                            </select>
                            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="invoice_prefix">{{ __('Invoice number prefix') }}</label>
                        <input type="text" name="invoice_prefix" id="invoice_prefix" maxlength="10"
                            class="form-control text-uppercase @error('invoice_prefix') is-invalid @enderror"
                            placeholder="{{ config('invoicing.prefix', 'INV') }}" value="{{ old('invoice_prefix', $business->invoice_prefix) }}">
                        @error('invoice_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Applies to new invoices only. Numbers already issued keep theirs.') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="receipt_header">{{ __('Receipt header') }}</label>
                        <textarea name="receipt_header" id="receipt_header" rows="2" maxlength="500"
                            class="form-control @error('receipt_header') is-invalid @enderror">{{ old('receipt_header', $business->receipt_header) }}</textarea>
                        @error('receipt_header')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="receipt_footer">{{ __('Receipt footer') }}</label>
                        <textarea name="receipt_footer" id="receipt_footer" rows="2" maxlength="500"
                            class="form-control @error('receipt_footer') is-invalid @enderror">{{ old('receipt_footer', $business->receipt_footer) }}</textarea>
                        @error('receipt_footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="logo">{{ __('Logo') }}</label>
                        @if ($business->logo_path)
                            <div class="mb-2">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($business->logo_path) }}" alt="" style="max-height: 60px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                                    <label class="form-check-label" for="remove_logo">{{ __('Remove the logo') }}</label>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp"
                            class="form-control @error('logo') is-invalid @enderror">
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <x-ui-button variant="primary" type="submit">{{ __('Save') }}</x-ui-button>
                </form>
            </x-ui-card>
        </div>
    </div>
@endsection
