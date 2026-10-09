{{-- Shared audit table body. Expects $logs (Collection or Paginator of AuditLog). --}}
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
                        <div class="text-muted small font-monospace">{{ Str::limit($log->auditable_uuid, 13) }}</div>
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
                    <td colspan="6" class="text-center text-muted py-4">{{ __('No audit entries found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
