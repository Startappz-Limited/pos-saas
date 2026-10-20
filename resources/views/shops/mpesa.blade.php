@extends('layouts.app')

@section('title', 'M-Pesa')

@section('content')
    <div class="row">
        <div class="col-lg-6">
            <x-ui-card title="{{ __('M-Pesa for :shop', ['shop' => $shop->name]) }}">
                <p class="text-muted">
                    {{ __('Customers are told to pay this Till or Paybill. We check the number with M-Pesa, using your account details, before saving it. The details are stored encrypted and never shown again.') }}
                </p>

                @if ($shop->mpesa_verified_at)
                    <div class="alert alert-success py-2">
                        {{ ucfirst($shop->mpesa_shortcode_type) }} <strong>{{ $shop->mpesa_shortcode }}</strong>
                        {{ __('checked :when', ['when' => $shop->mpesa_verified_at->diffForHumans()]) }}
                    </div>
                @endif

                <form method="POST" action="{{ route('shops.mpesa.update', $shop) }}" autocomplete="off">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="type">{{ __('Type') }}</label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach (['till' => __('Till (Buy Goods)'), 'paybill' => __('Paybill')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $shop->mpesa_shortcode_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <x-ui-form-input name="shortcode" label="{{ __('Till or Paybill number') }}" inputmode="numeric"
                        value="{{ old('shortcode', $shop->mpesa_shortcode) }}" required />
                    <x-ui-form-input name="consumer_key" label="{{ __('Consumer key') }}"
                        placeholder="{{ $shop->mpesa_verified_at ? __('Leave blank to keep the saved one') : '' }}" />
                    <x-ui-form-input name="consumer_secret" label="{{ __('Consumer secret') }}" type="password"
                        placeholder="{{ $shop->mpesa_verified_at ? __('Leave blank to keep the saved one') : '' }}" />
                    <x-ui-form-input name="passkey" label="{{ __('Passkey (if your account has one)') }}" type="password" />

                    <x-ui-button variant="primary" type="submit">{{ __('Check and save') }}</x-ui-button>
                    <a href="{{ route('shops.edit', $shop) }}" class="btn btn-light">{{ __('Back to the shop') }}</a>
                </form>
            </x-ui-card>
        </div>
    </div>
@endsection
