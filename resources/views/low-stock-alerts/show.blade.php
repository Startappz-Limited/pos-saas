@extends('layouts.app')

@section('title', 'Low Stock Alert')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="{{ route('low-stock-alerts.index') }}">Low Stock Alerts</a></li>
                                <li class="breadcrumb-item active">Details</li>
                            </ol>
                        </nav>
                        <h4 class="mb-0">Low Stock Alert</h4>
                    </div>
                    <div class="d-flex gap-2">
                        @if ($lowStockAlert->isPending())
                            @can('acknowledge', $lowStockAlert)
                                <form action="{{ route('low-stock-alerts.acknowledge', $lowStockAlert) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning">
                                        <iconify-icon icon="solar:check-circle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Acknowledge
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Alert Details</h5>
                        @php
                            $statusColors = [
                                'pending' => 'danger',
                                'acknowledged' => 'warning',
                                'resolved' => 'success',
                                'ignored' => 'secondary',
                            ];
                            $color = $statusColors[$lowStockAlert->status->value] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-13">
                            {{ ucfirst($lowStockAlert->status->value) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Product:</td>
                                        <td class="fw-medium">
                                            @if ($lowStockAlert->product)
                                                {{ $lowStockAlert->product->name }}
                                                @if ($lowStockAlert->variation)
                                                    <br><small class="text-muted">{{ $lowStockAlert->variation->name }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $lowStockAlert->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Current Quantity:</td>
                                        <td><span class="text-danger fw-medium">{{ number_format($lowStockAlert->current_quantity) }}</span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Threshold:</td>
                                        <td>{{ number_format($lowStockAlert->threshold_quantity) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td>{{ $lowStockAlert->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                    @if ($lowStockAlert->acknowledger)
                                        <tr>
                                            <td class="text-muted">Acknowledged By:</td>
                                            <td>{{ $lowStockAlert->acknowledger->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Acknowledged At:</td>
                                            <td>{{ $lowStockAlert->acknowledged_at?->format('M d, Y H:i') }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection