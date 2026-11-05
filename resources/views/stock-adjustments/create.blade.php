@extends('layouts.app')

@section('title', __('Create Stock Adjustment'))

@section('content')
    <div>
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('stock-adjustments.index') }}">{{ __('Stock Adjustments') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Create New') }}</li>
                    </ol>
                </nav>
                <h4 class="mb-0">{{ __('Create Stock Adjustment') }}</h4>
            </div>
        </div>

        <form action="{{ route('stock-adjustments.store') }}" method="POST" id="stock-adjustment-form">
            @csrf
            @honeypot
            @include('stock-adjustments._form')
        </form>
    </div>
@endsection
