@extends('layouts.app')

@section('title', 'Stock Movement Details')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('stock-movements.index') }}">Stock Movements</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Stock Movement Details</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Movement Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Product:</td>
                                        <td class="fw-medium">
                                            @if ($stockMovement->product)
                                                {{ $stockMovement->product->name }}
                                                @if ($stockMovement->variation)
                                                    <br><small class="text-muted">{{ $stockMovement->variation->name }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $stockMovement->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Type:</td>
                                        <td>
                                            <span class="badge bg-{{ $stockMovement->movement_type->value === 'in' ? 'success' : 'danger' }}-subtle text-{{ $stockMovement->movement_type->value === 'in' ? 'success' : 'danger' }}">
                                                {{ ucfirst($stockMovement->movement_type->value) }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Quantity:</td>
                                        <td class="fw-medium">{{ number_format($stockMovement->quantity) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created:</td>
                                        <td>{{ $stockMovement->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Created By:</td>
                                        <td>{{ $stockMovement->creator?->name ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($stockMovement->notes)
                            <div class="mt-3 p-3 bg-light rounded">
                                <strong class="text-muted">Notes:</strong>
                                <p class="mb-0 mt-1">{{ $stockMovement->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection