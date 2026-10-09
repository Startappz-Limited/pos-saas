@extends('layouts.app')

@section('title', 'Stock Movements')

@section('content')
    <div>
        <!-- Stock Movements List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Stock Movement History</h4>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Shop</th>
                                        <th>Type</th>
                                        <th class="text-center">Quantity</th>
                                        <th>Reference</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($movements as $movement)
                                        <tr>
                                            <td>{{ $movement->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                                            <td>
                                                @if ($movement->product)
                                                    {{ $movement->product->name }}
                                                    @if ($movement->variation)
                                                        <br><small class="text-muted">{{ $movement->variation->name }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $movement->shop?->name ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $movement->movement_type->value === 'in' ? 'success' : 'danger' }}-subtle text-{{ $movement->movement_type->value === 'in' ? 'success' : 'danger' }}">
                                                    {{ ucfirst($movement->movement_type->value) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if($movement->movement_type->value === 'in')
                                                    <span class="text-success">+{{ number_format($movement->quantity) }}</span>
                                                @else
                                                    <span class="text-danger">-{{ number_format($movement->quantity) }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($movement->reference_type)
                                                    <small class="text-muted">{{ class_basename($movement->reference_type) }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $movement->creator?->name ?? '—' }}</td>
                                            <td>
                                                <a href="{{ route('stock-movements.show', $movement) }}"
                                                    class="btn btn-light btn-sm" title="View">
                                                    <iconify-icon icon="solar:eye-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <iconify-icon icon="solar:box-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No stock movements found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($movements->hasPages())
                            <div class="mt-4">
                                {{ $movements->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection