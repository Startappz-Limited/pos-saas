@extends('layouts.app')

@section('title', __(':entity History', ['entity' => class_basename($auditableType)]))

@section('content')
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">
                    {{ __(':entity History', ['entity' => class_basename($auditableType)]) }}
                </h5>
                <p class="text-muted small font-monospace mb-0">{{ $auditableUuid }}</p>
            </div>

            <a href="{{ route('audit-logs.index') }}"
                class="btn btn-sm btn-outline-secondary">{{ __('Full audit trail') }}</a>
        </div>

        <div class="card-body p-0">
            @include('audit-logs.partials.rows', ['logs' => $logs])
        </div>

        @if ($logs->isNotEmpty())
            <div class="card-footer text-muted small">
                {{ trans_choice('{1}:count entry|[2,*]:count entries', $logs->count(), ['count' => $logs->count()]) }}
            </div>
        @endif
    </div>
@endsection
