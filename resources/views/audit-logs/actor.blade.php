@extends('layouts.app')

@section('title', __('Activity: :name', ['name' => $actor->name]))

@section('content')
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">{{ __('Activity: :name', ['name' => $actor->name]) }}</h5>
                <p class="text-muted small mb-0">{{ $actor->email }}</p>
            </div>

            <a href="{{ route('audit-logs.index') }}"
                class="btn btn-sm btn-outline-secondary">{{ __('Full audit trail') }}</a>
        </div>

        <div class="card-body p-0">
            @include('audit-logs.partials.rows', ['logs' => $logs])
        </div>

        @if ($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
