@extends('layouts.app')

@section('title', 'Inventory Snapshot')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('inventory-snapshots.index') }}">Inventory Snapshots</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Inventory Snapshot</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Snapshot Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Product:</td>
                                        <td class="fw-medium">
                                            @if ($inventorySnapshot->product)
                                                {{ $inventorySnapshot->product->name }}
                                                @if ($inventorySnapshot->variation)
                                                    <br><small class="text-muted">{{ $inventorySnapshot->variation->name }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Shop:</td>
                                        <td>{{ $inventorySnapshot->shop?->name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Snapshot Date:</td>
                                        <td>{{ $inventorySnapshot->snapshot_date?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 150px;">Quantity:</td>
                                        <td class="fw-medium">{{ number_format($inventorySnapshot->quantity_on_hand) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Total Value:</td>
                                        <td>{{ format_currency($inventorySnapshot->total_value) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Retail Value:</td>
                                        <td>{{ format_currency($inventorySnapshot->retail_value) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection