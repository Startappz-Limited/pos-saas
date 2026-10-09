@extends('layouts.app')

@section('title', __('Payment :number', ['number' => $payment->payment_number]))

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">{{ $payment->payment_number }}</h5>
                    <span class="badge bg-success">{{ __('Received') }}</span>
                </div>

                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Amount') }}</dt>
                        <dd class="col-sm-8 fs-5 fw-semibold">{{ format_currency($payment->amount) }}</dd>

                        <dt class="col-sm-4">{{ __('Method') }}</dt>
                        <dd class="col-sm-8">{{ $payment->payment_method_label }}</dd>

                        <dt class="col-sm-4">{{ __('Reference') }}</dt>
                        <dd class="col-sm-8">{{ $payment->reference ?: '—' }}</dd>

                        <dt class="col-sm-4">{{ __('Received at') }}</dt>
                        <dd class="col-sm-8">{{ $payment->paid_at?->toDayDateTimeString() }}</dd>

                        <dt class="col-sm-4">{{ __('Received by') }}</dt>
                        <dd class="col-sm-8">{{ $payment->receiver?->name ?? '—' }}</dd>

                        @if ($payment->notes)
                            <dt class="col-sm-4">{{ __('Notes') }}</dt>
                            <dd class="col-sm-8">{{ $payment->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">{{ __('Sale') }}</h6>
                </div>
                <div class="card-body">
                    @if ($payment->sale)
                        <dl class="row mb-3">
                            <dt class="col-sm-5">{{ __('Invoice') }}</dt>
                            <dd class="col-sm-7">
                                <a href="{{ route('sales.show', $payment->sale) }}">
                                    {{ $payment->sale->invoice_number }}
                                </a>
                            </dd>

                            <dt class="col-sm-5">{{ __('Customer') }}</dt>
                            <dd class="col-sm-7">{{ $payment->sale->customer?->name ?? __('Walk-in') }}</dd>

                            <dt class="col-sm-5">{{ __('Shop') }}</dt>
                            <dd class="col-sm-7">{{ $payment->sale->shop?->name ?? '—' }}</dd>

                            <dt class="col-sm-5">{{ __('Sale total') }}</dt>
                            <dd class="col-sm-7">{{ format_currency($payment->sale->total_amount) }}</dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">{{ __('The related sale is no longer available.') }}</p>
                    @endif

                    <a href="{{ route('payments.index') }}" class="btn btn-link px-0">{{ __('Back to payments') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
