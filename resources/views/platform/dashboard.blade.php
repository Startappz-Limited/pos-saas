@extends('layouts.platform')

@section('title', __('Overview'))

@section('content')
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <x-ui-card>
                <p class="text-muted mb-1">{{ __('Businesses') }}</p>
                <h3 class="mb-0">{{ $total }}</h3>
            </x-ui-card>
        </div>
        @foreach ($byStatus as $status => $count)
            <div class="col-md-6 col-xl-3">
                <x-ui-card>
                    <p class="text-muted mb-1">{{ \Illuminate\Support\Str::headline($status) }}</p>
                    <h3 class="mb-0">{{ $count }}</h3>
                </x-ui-card>
            </div>
        @endforeach
        <div class="col-md-6 col-xl-3">
            <x-ui-card>
                <p class="text-muted mb-1">{{ __('Platform operators') }}</p>
                <h3 class="mb-0">{{ $operators }}</h3>
            </x-ui-card>
        </div>
    </div>

    <x-ui-alert variant="info">
        {{ __('Every action in this console, reads included, is written to the audit log with your name and the business it concerns.') }}
    </x-ui-alert>
@endsection
