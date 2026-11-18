@extends('layouts.app')

@section('title', __('Quarantine'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Quarantined stock') }}</h5>
                    <p class="text-muted small mb-0 mt-1">
                        {{ __('Quarantined stock is on hand but is never sold. It leaves quarantine only through an approved release, which keeps its batch and expiry. Medical stock that is known to be expired cannot be released; it leaves through disposal or a supplier return.') }}
                    </p>
                </div>
                <div class="card-body">
                    @if ($layers->isEmpty())
                        <p class="text-muted mb-0">{{ __('Nothing is in quarantine.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <th>{{ __('Shop') }}</th>
                                        <th>{{ __('Batch') }}</th>
                                        <th>{{ __('Expiry') }}</th>
                                        <th class="text-end">{{ __('Quantity') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($layers as $layer)
                                        <tr>
                                            <td>
                                                {{ $layer->product?->name ?? '—' }}
                                                @if ($layer->product?->is_medical)
                                                    <span class="badge bg-info-subtle text-info">{{ __('Medical') }}</span>
                                                @endif
                                                @if ($layer->variation && ! $layer->variation->is_default)
                                                    <br><small class="text-muted">{{ $layer->variation->name }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $layer->shop?->name ?? '—' }}</td>
                                            <td>{{ $layer->batch_number ?? __('Unknown') }}</td>
                                            <td>{{ $layer->expiry_date?->format('Y-m-d') ?? __('Unknown') }}</td>
                                            <td class="text-end">{{ $layer->remaining_quantity }}</td>
                                            <td>
                                                <span class="badge bg-warning-subtle text-warning">{{ $layer->quarantine_status->label() }}</span>
                                                @if ($layer->quarantine_reason)
                                                    <br><small class="text-muted">{{ $layer->quarantine_reason }}</small>
                                                @endif
                                            </td>
                                            <td style="min-width: 320px">
                                                @can('stock.release-quarantine')
                                                    @if ($layer->quarantine_status === \App\Enums\QuarantineStatus::UNVERIFIED)
                                                        <form method="POST" action="{{ route('stock-quarantine.verify', $layer) }}" class="row g-1 mb-2">
                                                            @csrf
                                                            <div class="col-6">
                                                                <input type="text" name="batch_number" class="form-control form-control-sm" placeholder="{{ __('Batch number') }}" required maxlength="100" aria-label="{{ __('Batch number') }}">
                                                            </div>
                                                            <div class="col-4">
                                                                <input type="date" name="expiry_date" class="form-control form-control-sm" required aria-label="{{ __('Expiry date') }}">
                                                            </div>
                                                            <div class="col-2">
                                                                <button type="submit" class="btn btn-sm btn-soft-primary w-100">{{ __('Verify') }}</button>
                                                            </div>
                                                        </form>
                                                    @endif

                                                    @if ($blocked->get($layer->id))
                                                        <small class="text-danger">{{ $blocked->get($layer->id) }}</small>
                                                    @else
                                                        <form method="POST" action="{{ route('stock-quarantine.release', $layer) }}" class="row g-1">
                                                            @csrf
                                                            <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                                            <div class="col-3">
                                                                <input type="number" name="quantity" class="form-control form-control-sm" step="0.001" min="0.001" max="{{ $layer->remaining_quantity }}" value="{{ $layer->remaining_quantity }}" required aria-label="{{ __('Quantity to release') }}">
                                                            </div>
                                                            <div class="col-7">
                                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('Why it is safe to release') }}" required maxlength="500" aria-label="{{ __('Reason for release') }}">
                                                            </div>
                                                            <div class="col-2">
                                                                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Release') }}</button>
                                                            </div>
                                                        </form>
                                                    @endif
                                                @else
                                                    <small class="text-muted">{{ __('Read only') }}</small>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $layers->links() }}</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Recent releases') }}</h5>
                </div>
                <div class="card-body">
                    @if ($releases->isEmpty())
                        <p class="text-muted mb-0">{{ __('No stock has been released from quarantine yet.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Approved') }}</th>
                                        <th>{{ __('Product') }}</th>
                                        <th class="text-end">{{ __('Quantity') }}</th>
                                        <th>{{ __('Was') }}</th>
                                        <th>{{ __('Batch') }}</th>
                                        <th>{{ __('Expiry') }}</th>
                                        <th>{{ __('Approved by') }}</th>
                                        <th>{{ __('Reason') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($releases as $release)
                                        <tr>
                                            <td>{{ $release->approved_at->format('Y-m-d H:i') }}</td>
                                            <td>{{ $release->product?->name ?? '—' }}</td>
                                            <td class="text-end">{{ $release->quantity }}</td>
                                            <td>{{ $release->quarantine_status->label() }}</td>
                                            <td>{{ $release->batch_number ?? __('Unknown') }}</td>
                                            <td>{{ $release->expiry_date?->format('Y-m-d') ?? __('Unknown') }}</td>
                                            <td>{{ $release->approver?->name ?? '—' }}</td>
                                            <td>{{ $release->reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
