@extends('layouts.app')

@section('title', __('Audit Trail'))

@section('content')
    <div>
        <div class="row">
            @foreach ([
                ['label' => __('Entries'), 'value' => $statistics['total'], 'tone' => 'primary', 'icon' => 'solar:document-text-bold-duotone'],
                ['label' => __('Created'), 'value' => $statistics['created'], 'tone' => 'success', 'icon' => 'solar:add-circle-bold-duotone'],
                ['label' => __('Updated'), 'value' => $statistics['updated'], 'tone' => 'info', 'icon' => 'solar:pen-bold-duotone'],
                ['label' => __('Deleted'), 'value' => $statistics['deleted'], 'tone' => 'danger', 'icon' => 'solar:trash-bin-trash-bold-duotone'],
            ] as $tile)
                <div class="col-xl-3 col-md-6">
                    <div class="card card-height-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    <span
                                        class="avatar-title bg-{{ $tile['tone'] }}-subtle text-{{ $tile['tone'] }} rounded fs-3">
                                        <iconify-icon icon="{{ $tile['icon'] }}" aria-hidden="true"></iconify-icon>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-uppercase fw-medium text-muted mb-1">{{ $tile['label'] }}</p>
                                    <h4 class="mb-0">{{ number_format($tile['value']) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h5 class="card-title mb-0">{{ __('Audit Trail') }}</h5>

                @can('export', App\Models\AuditLog::class)
                    <form method="POST" action="{{ route('audit-logs.export') }}">
                        @csrf
                        @honeypot
                        @foreach (array_filter($filters ?? []) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <iconify-icon icon="solar:download-outline" aria-hidden="true"></iconify-icon>
                            {{ __('Export CSV') }}
                        </button>
                    </form>
                @endcan
            </div>

            <div class="card-body border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-entity" class="form-label">{{ __('Entity') }}</label>
                        <select id="filter-entity" name="auditable_type" class="form-select">
                            <option value="">{{ __('All entities') }}</option>
                            @foreach ($auditableTypes as $auditableType)
                                <option value="{{ $auditableType }}" @selected(($filters['auditable_type'] ?? null) === $auditableType)>
                                    {{ class_basename($auditableType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filter-action" class="form-label">{{ __('Action') }}</label>
                        <select id="filter-action" name="event" class="form-select">
                            <option value="">{{ __('All actions') }}</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->value }}" @selected(($filters['event'] ?? null) === $event->value)>
                                    {{ $event->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="filter-from" class="form-label">{{ __('From') }}</label>
                        <input type="date" id="filter-from" name="from" class="form-control"
                            value="{{ $filters['from'] ?? '' }}">
                    </div>

                    <div class="col-md-2">
                        <label for="filter-to" class="form-label">{{ __('To') }}</label>
                        <input type="date" id="filter-to" name="to" class="form-control"
                            value="{{ $filters['to'] ?? '' }}">
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">{{ __('Filter') }}</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('When') }}</th>
                                <th scope="col">{{ __('Entity') }}</th>
                                <th scope="col">{{ __('Action') }}</th>
                                <th scope="col">{{ __('Actor') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="text-end">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr>
                                    <td>
                                        {{ $log->created_at?->format('M d, Y H:i:s') }}
                                        <div class="text-muted small">{{ $log->ip_address }}</div>
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $log->auditable_label }}</span>
                                        <div class="text-muted small font-monospace">
                                            {{ Str::limit($log->auditable_uuid, 13) }}
                                        </div>
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $log->event?->label() }}</span></td>
                                    <td>{{ $log->user_name ?? __('System') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $log->status?->color() ?? 'secondary' }}">
                                            {{ $log->status?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('audit-logs.show', $log) }}"
                                            class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        {{ __('No audit entries found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($logs->hasPages())
                <div class="card-footer">{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
@endsection
