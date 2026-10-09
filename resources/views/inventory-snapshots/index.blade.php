@extends('layouts.app')

@section('title', 'Inventory Snapshots')

@section('content')
    <div>
        <!-- Inventory Snapshots List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Inventory Snapshots</h4>
                    </div>

                    <div class="card-body">
                        <!-- Search and Filter Form -->
                        <form method="GET" action="{{ route('inventory-snapshots.index') }}" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <select name="shop_id" class="form-select">
                                    <option value="">All Shops</option>
                                    @foreach ($shops as $shop)
                                        <option value="{{ $shop->id }}"
                                            {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <iconify-icon icon="solar:magnifer-linear" class="align-middle"></iconify-icon> Search
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Shop</th>
                                        <th>Product</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Total Value</th>
                                        <th class="text-end">Retail Value</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($snapshots as $snapshot)
                                        <tr>
                                            <td>{{ $snapshot->snapshot_date?->format('M d, Y') ?? '—' }}</td>
                                            <td>{{ $snapshot->shop?->name ?? '—' }}</td>
                                            <td>
                                                @if ($snapshot->product)
                                                    {{ $snapshot->product->name }}
                                                    @if ($snapshot->variation)
                                                        <br><small class="text-muted">{{ $snapshot->variation->name }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ number_format($snapshot->quantity_on_hand) }}</td>
                                            <td class="text-end">{{ format_currency($snapshot->total_value) }}</td>
                                            <td class="text-end">{{ format_currency($snapshot->retail_value) }}</td>
                                            <td>
                                                <a href="{{ route('inventory-snapshots.show', $snapshot) }}"
                                                    class="btn btn-light btn-sm" title="View">
                                                    <iconify-icon icon="solar:eye-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <iconify-icon icon="solar:document-bold-duotone"
                                                    class="fs-1 text-muted mb-2"></iconify-icon>
                                                <p class="text-muted">No inventory snapshots found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($snapshots->hasPages())
                            <div class="mt-4">
                                {{ $snapshots->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection