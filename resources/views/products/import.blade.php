@extends('layouts.app')

@section('title', 'Import products')

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <x-ui-card title="{{ __('Import products from a CSV file') }}">
                <p class="text-muted">
                    {{ __('The first row names the columns. Required: name, selling_price. Optional: sku, barcode, category, cost_price, wholesale_price, stock, reorder_level, unit. Nothing is imported if any line has a problem.') }}
                    <a href="{{ route('products.import.template') }}">{{ __('Download a template') }}</a>
                </p>

                @if (session('import_errors'))
                    <div class="alert alert-danger">
                        <strong>{{ __('Nothing was imported:') }}</strong>
                        <ul class="mb-0">
                            @foreach (session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="shop_id">{{ __('Shop (owns the products and receives the stock)') }}</label>
                        <select name="shop_id" id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" required>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                        @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="file">{{ __('CSV file') }}</label>
                        <input type="file" name="file" id="file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-ui-button variant="primary" type="submit">{{ __('Import') }}</x-ui-button>
                </form>
            </x-ui-card>
        </div>
    </div>
@endsection
