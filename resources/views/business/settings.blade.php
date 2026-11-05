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

                    <h5 class="mt-4 mb-3">{{ __('Sales') }}</h5>
                    @if (auth()->user()->isBusinessOwner())
                    @php($voidChoice = old('void_window', is_int($voidWindow) ? 'minutes' : $voidWindow))
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="void_window">{{ __('How long a sale can be voided') }}</label>
                            <select name="void_window" id="void_window" class="form-select @error('void_window') is-invalid @enderror">
                                <option value="session" @selected($voidChoice === 'session')>{{ __('Until the register session closes (default)') }}</option>
                                <option value="end_of_day" @selected($voidChoice === 'end_of_day')>{{ __('Until the end of the day') }}</option>
                                <option value="minutes" @selected($voidChoice === 'minutes')>{{ __('A number of minutes') }}</option>
                                <option value="none" @selected($voidChoice === 'none')>{{ __('No time limit') }}</option>
                            </select>
                            @error('void_window')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="void_window_minutes">{{ __('Minutes') }}</label>
                            <input type="number" name="void_window_minutes" id="void_window_minutes" min="1" max="1440" step="1"
                                class="form-control @error('void_window_minutes') is-invalid @enderror"
                                value="{{ old('void_window_minutes', is_int($voidWindow) ? $voidWindow : '') }}">
                            @error('void_window_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label" for="void_window_readonly">{{ __('How long a sale can be voided') }}</label>
                            <input type="text" id="void_window_readonly" class="form-control" disabled
                                value="{{ match (true) {
                                    is_int($voidWindow) => __(':minutes minutes', ['minutes' => $voidWindow]),
                                    $voidWindow === 'end_of_day' => __('Until the end of the day'),
                                    $voidWindow === 'none' => __('No time limit'),
                                    default => __('Until the register session closes (default)'),
                                } }}">
                            <div class="form-text">{{ __('Only the owner can change it.') }}</div>
                        </div>
                    @endif
                    <div class="form-text mb-3">{{ __('The day is the shop\'s local day. Past the limit, use a return. The limit applies to the owner too.') }}</div>

                    <h5 class="mt-4 mb-3">{{ __('Stock') }}</h5>
                    <div class="mb-3">
                        <label class="form-label" for="adjustment_approval_threshold">{{ __('Adjustment approval threshold') }}</label>
                        @if (auth()->user()->isBusinessOwner())
                            <input type="number" name="adjustment_approval_threshold" id="adjustment_approval_threshold" min="0" step="0.01"
                                class="form-control @error('adjustment_approval_threshold') is-invalid @enderror"
                                value="{{ old('adjustment_approval_threshold', $adjustmentThreshold) }}">
                        @else
                            <input type="text" id="adjustment_approval_threshold" class="form-control" value="{{ number_format($adjustmentThreshold, 2) }}" disabled>
                            <div class="form-text">{{ __('Only the owner can change it.') }}</div>
                        @endif
                        <div class="form-text">{{ __('A stock adjustment worth this much or more at cost waits for a second person to approve it. Loss, theft, damage, expiry and unknown costs always do.') }}</div>
                        @error('adjustment_approval_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <x-ui-button variant="primary" type="submit">{{ __('Save') }}</x-ui-button>
                </form>
            </x-ui-card>
        </div>
    </div>
@endsection
