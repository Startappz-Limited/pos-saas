@extends('layouts.app')

@section('title', __('Audit Entry'))

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">{{ $log->auditable_label }} · {{ $log->event?->label() }}</h5>
                    <span class="badge bg-{{ $log->status?->color() ?? 'secondary' }}">
                        {{ $log->status?->label() ?? '—' }}
                    </span>
                </div>

                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('When') }}</dt>
                        <dd class="col-sm-8">{{ $log->created_at?->toDayDateTimeString() }}</dd>

                        <dt class="col-sm-4">{{ __('Entity') }}</dt>
                        <dd class="col-sm-8">{{ $log->auditable_type }}</dd>

                        <dt class="col-sm-4">{{ __('Record') }}</dt>
                        <dd class="col-sm-8 font-monospace small">{{ $log->auditable_uuid ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('Actor') }}</dt>
                        <dd class="col-sm-8">
                            {{ $log->user_name ?? __('System') }}
                            @if ($log->user_id)
                                <div class="text-muted small font-monospace">{{ $log->user_id }}</div>
                            @endif
                        </dd>

                        <dt class="col-sm-4">{{ __('IP address') }}</dt>
                        <dd class="col-sm-8">{{ $log->ip_address ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('User agent') }}</dt>
                        <dd class="col-sm-8 small text-muted">{{ $log->user_agent ?? '—' }}</dd>
                    </dl>
                </div>

                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-link px-0">{{ __('Back to trail') }}</a>

                    @if ($log->auditable_uuid)
                        <a href="{{ route('audit-logs.forModel', ['type' => class_basename($log->auditable_type), 'id' => $log->auditable_uuid]) }}"
                            class="btn btn-sm btn-outline-secondary ms-auto">
                            {{ __('Full history for this record') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">{{ __('Changes') }}</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">{{ __('Field') }}</th>
                                    <th scope="col">{{ __('Before') }}</th>
                                    <th scope="col">{{ __('After') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $fields = collect(array_keys(($log->old_values ?? []) + ($log->new_values ?? [])))->sort();
                                @endphp

                                @forelse ($fields as $field)
                                    <tr>
                                        <td class="fw-medium">{{ $field }}</td>
                                        <td class="text-muted">
                                            {{ \App\Support\AuditValue::render($log->old_values[$field] ?? null) }}
                                        </td>
                                        <td>
                                            {{ \App\Support\AuditValue::render($log->new_values[$field] ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            {{ __('No field-level changes were recorded.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
