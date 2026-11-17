@extends('layouts.app')

@section('title', __('Units'))

@section('content')
    <div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1">{{ __('Units of :product', ['product' => $product->name]) }}</h4>
                <p class="text-muted mb-0">{{ __('How this product is sold and bought. The base unit is always 1 and is kept. A unit that has been sold can\'t change its pack size.') }}</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('products.edit', $product) }}">{{ __('Back to the product') }}</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        @foreach ($variations as $variation)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        {{ $variation->is_default ? $product->name : $variation->name }}
                        @if ($variation->is_default)
                            <span class="text-muted fs-13">{{ __('(the product itself)') }}</span>
                        @endif
                    </h5>
                </div>
                @foreach ($variation->units as $unit)
                    <form id="unit-form-{{ $unit->id }}" method="POST" action="{{ route('products.units.update', [$product, $unit]) }}" class="d-none">
                        @csrf
                        @method('PUT')
                    </form>
                @endforeach
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th class="text-end">{{ __('Base units per unit') }}</th>
                                <th class="text-end">{{ __('Decimals') }}</th>
                                <th>{{ __('Barcode') }}</th>
                                <th class="text-end">{{ __('Price') }}</th>
                                <th>{{ __('Sell / buy') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variation->units as $unit)
                                <tr>
                                        <td><input form="unit-form-{{ $unit->id }}" class="form-control form-control-sm" name="name" value="{{ $unit->name }}" maxlength="60" required></td>
                                        <td class="text-end">
                                            <input form="unit-form-{{ $unit->id }}" class="form-control form-control-sm text-end" name="factor" value="{{ $unit->factor }}" type="number" step="0.001" min="0.001" @disabled($unit->is_base || $unit->isUsed())>
                                        </td>
                                        <td class="text-end" style="width: 6rem">
                                            <input form="unit-form-{{ $unit->id }}" class="form-control form-control-sm text-end" name="precision" value="{{ $unit->precision }}" type="number" min="0" max="3" required>
                                        </td>
                                        <td><input form="unit-form-{{ $unit->id }}" class="form-control form-control-sm" name="barcode" value="{{ $unit->barcode }}" maxlength="100"></td>
                                        <td class="text-end" style="width: 8rem">
                                            <input form="unit-form-{{ $unit->id }}" class="form-control form-control-sm text-end" name="selling_price" value="{{ $unit->selling_price }}" type="number" step="0.01" min="0">
                                        </td>
                                        <td>
                                            <div class="form-check form-check-inline"><input form="unit-form-{{ $unit->id }}" class="form-check-input" type="checkbox" name="can_sell" value="1" @checked($unit->can_sell)><label class="form-check-label">{{ __('Sell') }}</label></div>
                                            <div class="form-check form-check-inline"><input form="unit-form-{{ $unit->id }}" class="form-check-input" type="checkbox" name="can_buy" value="1" @checked($unit->can_buy)><label class="form-check-label">{{ __('Buy') }}</label></div>
                                        </td>
                                        <td class="text-nowrap">
                                            <button type="submit" form="unit-form-{{ $unit->id }}" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                                    @unless ($unit->is_base)
                                        <form method="POST" action="{{ route('products.units.destroy', [$product, $unit]) }}" class="d-inline"
                                            onsubmit="return confirm(@js(__('Remove this unit? Past sales still show it.')));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button>
                                        </form>
                                    @endunless
                                        </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('products.units.store', $product) }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="variation" value="{{ $variation->uuid }}">
                        <div class="col-md-2">
                            <label class="form-label" for="new-name-{{ $variation->id }}">{{ __('Name') }}</label>
                            <input class="form-control" id="new-name-{{ $variation->id }}" name="name" maxlength="60" required placeholder="{{ __('e.g. Crate of 24') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="new-factor-{{ $variation->id }}">{{ __('Base units per unit') }}</label>
                            <input class="form-control" id="new-factor-{{ $variation->id }}" name="factor" type="number" step="0.001" min="0.001" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label" for="new-precision-{{ $variation->id }}">{{ __('Decimals') }}</label>
                            <input class="form-control" id="new-precision-{{ $variation->id }}" name="precision" type="number" min="0" max="3" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="new-barcode-{{ $variation->id }}">{{ __('Barcode') }}</label>
                            <input class="form-control" id="new-barcode-{{ $variation->id }}" name="barcode" maxlength="100">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="new-price-{{ $variation->id }}">{{ __('Price') }}</label>
                            <input class="form-control" id="new-price-{{ $variation->id }}" name="selling_price" type="number" step="0.01" min="0">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">{{ __('Add unit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
