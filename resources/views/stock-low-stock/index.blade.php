@extends('layouts.app')

@section('title', __('Low stock'))

@section('content')
    <div>
        <div class="mb-3">
            <h4 class="mb-1">{{ __('Low stock') }}</h4>
            <p class="text-muted mb-0">{{ __('Products whose sellable stock at one shop is at or below its reorder level, largest shortfall first. Quantities are in base units. Expired and quarantined stock is not counted.') }}</p>
        </div>

        <form method="GET" action="{{ route('stock.low-stock') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="shop_id">{{ __('Shop') }}</label>
                <select class="form-select" name="shop_id" id="shop_id" onchange="this.form.submit()">
                    @foreach ($shops as $option)
                        <option value="{{ $option->id }}" @selected($shop && $shop->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('Show') }}</button>
            </div>
        </form>

        @if ($shop === null)
            <div class="alert alert-info">{{ __('No shop is available to show.') }}</div>
        @elseif ($rows->isEmpty())
            <div class="alert alert-success">{{ __('Nothing is low at :shop.', ['shop' => $shop->name]) }}</div>
        @else
            <form method="POST" action="{{ route('stock.low-stock.purchase-order') }}">
                @csrf
                @honeypot
                <input type="hidden" name="shop_id" value="{{ $shop->id }}">

                @if ($canOrder)
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-5">
                            <label class="form-label" for="supplier_id">{{ __('Supplier for the draft order') }}</label>
                            <select class="form-select" name="supplier_id" id="supplier_id" required>
                                <option value="">{{ __('Choose a supplier') }}</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-success">{{ __('Create purchase order') }}</button>
                        </div>
                    </div>
                @endif

                @error('lines')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    @if ($canOrder)
                                        <th style="width: 3rem;"><span class="visually-hidden">{{ __('Select') }}</span></th>
                                    @endif
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('SKU') }}</th>
                                    <th class="text-end">{{ __('Sellable') }}</th>
                                    <th class="text-end">{{ __('Reorder level') }}</th>
                                    <th class="text-end">{{ __('Shortfall') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        @if ($canOrder)
                                            <td>
                                                <input class="form-check-input" type="checkbox" name="lines[]"
                                                    value="{{ $row['key'] }}" aria-label="{{ __('Order :product', ['product' => $row['product_name']]) }}" checked>
                                            </td>
                                        @endif
                                        <td>
                                            {{ $row['product_name'] }}
                                            @if ($row['variation_name'])
                                                <small class="text-muted d-block">{{ $row['variation_name'] }}</small>
                                            @endif
                                            <small class="text-muted d-block">{{ $row['breakdown'] }}</small>
                                        </td>
                                        <td>{{ $row['sku'] }}</td>
                                        <td class="text-end">{{ \App\Models\Product::quantityLabel($row['sellable']) }}</td>
                                        <td class="text-end">{{ \App\Models\Product::quantityLabel($row['threshold']) }}</td>
                                        <td class="text-end fw-medium">{{ \App\Models\Product::quantityLabel($row['shortfall']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection
