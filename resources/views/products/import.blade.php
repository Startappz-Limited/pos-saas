@extends('layouts.app')

@section('title', __('Import products'))

@section('content')
    <div class="row">
        <div class="col-xl-5">
            <x-ui-card :title="__('Upload a CSV file')">
                <ol class="ps-3 text-muted mb-3">
                    <li>{{ __('Download the template, or use your own sheet with the column names on the right.') }}</li>
                    <li>{{ __('Fill in one product per row. Only name and selling price are required.') }}</li>
                    <li>{{ __('In Excel choose File → Save As → CSV; in Google Sheets, File → Download → CSV.') }}</li>
                    <li>{{ __('Choose the shop and upload. If some products are already in the system, you\'ll be asked before anything changes.') }}</li>
                </ol>

                <a href="{{ route('products.import.template') }}" class="btn btn-outline-primary btn-sm mb-3">
                    <iconify-icon icon="solar:download-minimalistic-broken" class="align-middle me-1" aria-hidden="true"></iconify-icon>
                    {{ __('Download template') }}
                </a>

                @if (session('import_errors'))
                    <div class="alert alert-danger" role="alert">
                        <strong>{{ __('Nothing was imported. Fix the file and upload it again:') }}</strong>
                        <ul class="mb-0">
                            @foreach (session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data">
                    @csrf
                    @honeypot
                    <div class="mb-3">
                        <label class="form-label" for="shop_id">{{ __('Shop') }}</label>
                        <select name="shop_id" id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" required
                            aria-describedby="shop_id-help">
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="shop_id-help">{{ __('New products belong to this shop, and the stock figures are this shop\'s.') }}</div>
                        @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="file">{{ __('CSV file') }}</label>
                        <input type="file" name="file" id="file" accept=".csv,text/csv" required
                            class="form-control @error('file') is-invalid @enderror">
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-ui-button variant="primary" type="submit">{{ __('Check and import') }}</x-ui-button>
                </form>
            </x-ui-card>
        </div>

        <div class="col-xl-7">
            <x-ui-card :title="__('Columns')">
                <p class="text-muted">
                    {{ __('The first row names the columns, in any order. Names are matched without regard to capitals or spaces, and the alternatives listed work too. Other columns are ignored, and you\'ll be told which.') }}
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Column') }}</th>
                                <th scope="col">{{ __('What it holds') }}</th>
                                <th scope="col">{{ __('Example') }}</th>
                                <th scope="col">{{ __('Also accepted') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($columns as $column)
                                <tr>
                                    <td class="text-nowrap">
                                        <code>{{ $column['name'] }}</code>
                                        @if ($column['required'])
                                            <x-ui-badge variant="danger" soft>{{ __('Required') }}</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="secondary" soft>{{ __('Optional') }}</x-ui-badge>
                                        @endif
                                    </td>
                                    <td class="small">{{ __($column['description']) }}</td>
                                    <td class="small text-nowrap">{{ $column['example'] }}</td>
                                    <td class="small">{{ implode(', ', $column['aliases']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui-card>
        </div>
    </div>
@endsection
