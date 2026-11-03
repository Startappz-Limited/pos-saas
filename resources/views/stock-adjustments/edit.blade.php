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

        @can('delete', $stockAdjustment)
            <div class="row">
                <div class="col-xl-4 offset-xl-8">
                    <div class="card mt-4 border-danger">
                        <div class="card-body">
                            <h6 class="text-danger">{{ __('Danger Zone') }}</h6>
                            <p class="text-muted small mb-3">{{ __('Deleting this adjustment cannot be undone.') }}</p>
                            <form action="{{ route('stock-adjustments.destroy', $stockAdjustment) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm w-100"
                                    onclick="return confirm(@js(__('Are you sure you want to delete this adjustment?')))">
                                    <iconify-icon icon="solar:trash-bin-trash-bold-duotone" class="align-middle me-1" aria-hidden="true"></iconify-icon>
                                    {{ __('Delete Adjustment') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection
