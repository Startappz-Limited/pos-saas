@extends('layouts.app')

@section('title', __('Confirm the import'))

@section('content')
    <x-ui-card :title="__('Some products are already in the system')">
        <p>
            {{ trans_choice(
                '{1} :file has 1 product whose SKU already exists, and :new new one.|[2,*] :file has :count products whose SKU already exists, and :new new ones.',
                $existing->count(),
                ['file' => $fileName, 'count' => $existing->count(), 'new' => $newCount],
            ) }}
            {{ __('Nothing has changed yet. Choose below what to do.') }}
        </p>
        <p class="text-muted small">
            {{ __('Updating sets each product\'s stock in :shop to the figure in the file (it is not added to what is there), and its low-stock threshold there. Names, prices, costs and categories stay as they are. The upload is kept until :time.', ['shop' => $shop->name, 'time' => $expiresAt->format('H:i')]) }}
        </p>

        @if ($ignored !== [])
            <x-ui-alert variant="warning">
                {{ __('Ignored columns: :columns. Their values won\'t be imported.', ['columns' => implode(', ', $ignored)]) }}
            </x-ui-alert>
        @endif

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Product') }}</th>
                        <th scope="col">{{ __('SKU') }}</th>
                        <th scope="col" class="text-end">{{ __('Stock in :shop', ['shop' => $shop->name]) }}</th>
                        <th scope="col" class="text-end">{{ __('Low-stock threshold') }}</th>
                        <th scope="col">{{ __('Result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($existing as $item)
                        @php($preview = $item['preview'])
                        <tr>
                            <td>{{ $item['product']->name }}</td>
                            <td class="text-nowrap"><code>{{ $item['product']->sku }}</code></td>
                            <td class="text-end text-nowrap">
                                {{ $preview['stock'] }}
                                @if ($preview['file_stock'] !== null && $preview['file_stock'] !== $preview['stock'])
                                    → <strong>{{ $preview['file_stock'] }}</strong>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                {{ $preview['threshold'] }}
                                @if ($preview['file_threshold'] !== null && $preview['file_threshold'] !== $preview['threshold'])
                                    → <strong>{{ $preview['file_threshold'] }}</strong>
                                @endif
                            </td>
                            <td>
                                @if ($preview['has_variations'])
                                    <x-ui-badge variant="secondary">{{ __('Has variations: not updated') }}</x-ui-badge>
                                @elseif ($preview['changes'])
                                    <x-ui-badge variant="info">{{ __('Will change') }}</x-ui-badge>
                                @else
                                    <x-ui-badge variant="success">{{ __('No change') }}</x-ui-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('products.import.decide') }}" class="d-flex flex-wrap gap-2">
            @csrf
            @honeypot
            <button type="submit" name="decision" value="update" class="btn btn-primary">
                {{ __('Update these and import the new ones') }}
            </button>
            <button type="submit" name="decision" value="new_only" class="btn btn-outline-primary">
                {{ __('Import the new ones only') }}
            </button>
            <button type="submit" name="decision" value="cancel" class="btn btn-light">
                {{ __('Cancel') }}
            </button>
        </form>
    </x-ui-card>
@endsection
