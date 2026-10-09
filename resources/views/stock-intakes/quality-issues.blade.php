@extends('layouts.app')

@section('title', 'Quality Issues - Stock Intakes')

@section('content')
    <div>

        <!-- Page Header -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Quality Issues</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('stock-intakes.index') }}">Stock Intakes</a></li>
                            <li class="breadcrumb-item active">Quality Issues</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <iconify-icon icon="solar:danger-triangle-bold-duotone"
                                class="align-middle text-danger me-1"></iconify-icon>
                            Intakes with Quality Issues
                            <span class="badge bg-danger-subtle text-danger ms-1">{{ $stockIntakes->count() }}</span>
                        </h5>
                        <a href="{{ route('stock-intakes.index') }}" class="btn btn-light btn-sm">
                            <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                            Back to All
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Intake #</th>
                                        <th>Product</th>
                                        <th>Supplier</th>
                                        <th>Qty Received</th>
                                        <th>Qty Rejected</th>
                                        <th>Rejection Rate</th>
                                        <th>Quality Status</th>
                                        <th>Quality Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($stockIntakes as $intake)
                                        <tr>
                                            <td>
                                                <a href="{{ route('stock-intakes.show', $intake) }}"
                                                    class="text-dark fw-medium">
                                                    {{ $intake->intake_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @if ($intake->product)
                                                    {{ $intake->product->name }}
                                                    <br><small class="text-muted">{{ $intake->product->sku }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($intake->supplier)
                                                    <a href="{{ route('suppliers.show', $intake->supplier) }}">
                                                        {{ $intake->supplier->name }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ number_format($intake->quantity_received, 2) }}</td>
                                            <td>
                                                <span
                                                    class="text-danger fw-medium">{{ number_format($intake->quantity_rejected, 2) }}</span>
                                            </td>
                                            <td>
                                                @php
                                                    $rejectionRate = $intake->getRejectionRate();
                                                    $rateColor =
                                                        $rejectionRate >= 20
                                                            ? 'danger'
                                                            : ($rejectionRate >= 10
                                                                ? 'warning'
                                                                : 'info');
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $rateColor }}-subtle text-{{ $rateColor }}">
                                                    {{ number_format($rejectionRate, 1) }}%
                                                </span>
                                            </td>
                                            <td>
                                                @php
                                                    $qualityColors = [
                                                        'passed' => 'success',
                                                        'partial' => 'warning',
                                                        'failed' => 'danger',
                                                        'poor' => 'danger',
                                                        'rejected' => 'danger',
                                                    ];
                                                    $qColor = $qualityColors[$intake->quality_status] ?? 'secondary';
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $qColor }}-subtle text-{{ $qColor }}">
                                                    {{ ucfirst($intake->quality_status ?? 'N/A') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-truncate d-inline-block" style="max-width: 200px;"
                                                    title="{{ $intake->quality_notes }}">
                                                    {{ $intake->quality_notes ?? '—' }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('stock-intakes.show', $intake) }}"
                                                    class="btn btn-light btn-sm" title="View Details">
                                                    <iconify-icon icon="solar:eye-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <iconify-icon icon="solar:check-circle-bold-duotone"
                                                    class="fs-1 text-success mb-2"></iconify-icon>
                                                <p class="text-muted">No quality issues found. All intakes passed quality
                                                    checks!</p>
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

    </div>
@endsection
