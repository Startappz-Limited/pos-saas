@extends('layouts.app')

@section('title', __('Edit Stock Adjustment: :number', ['number' => $stockAdjustment->adjustment_number]))

@section('content')
    <div>
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('stock-adjustments.index') }}">{{ __('Stock Adjustments') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('stock-adjustments.show', $stockAdjustment) }}">{{ $stockAdjustment->adjustment_number }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Edit') }}</li>
                    </ol>
                </nav>
                <h4 class="mb-0">{{ __('Edit Adjustment: :number', ['number' => $stockAdjustment->adjustment_number]) }}</h4>
            </div>
        </div>

        <form action="{{ route('stock-adjustments.update', $stockAdjustment) }}" method="POST" id="stock-adjustment-form">
            @csrf
            @honeypot
            @method('PUT')
            @include('stock-adjustments._form')
        </form>

        @can('cancel', $stockAdjustment)
            <div class="row">
                <div class="col-xl-4 offset-xl-8">
                    <div class="card mt-4">
                        <div class="card-body">
                            <p class="text-muted small mb-3">{{ __('Cancelling keeps the adjustment and its number, and moves no stock.') }}</p>
                            <form action="{{ route('stock-adjustments.cancel', $stockAdjustment) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-soft-secondary btn-sm w-100"
                                    onclick="return confirm(@js(__('Cancel this adjustment?')))">
                                    {{ __('Cancel Adjustment') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection
