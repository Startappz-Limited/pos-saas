@extends('layouts.app')

@section('title', 'Baileys Sessions')

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    @can('baileys.manage')
        @php
            $saleNotifyChannels = [
                App\Models\Shop::SALE_NOTIFY_INVOICE_PDF => [
                    'label' => __('Invoice PDF'),
                    'hint' => __('Baileys — no 24h window'),
                ],
                App\Models\Shop::SALE_NOTIFY_TEXT_RECEIPT => [
                    'label' => __('Text receipt'),
                    'hint' => __('Cloud API — 24h window applies'),
                ],
            ];
        @endphp

        <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Automatic Messages') }}</h4>
                <small class="text-muted">
                    {{ __('What a completed sale sends the customer. Turn everything off with the master switch, or pick channels individually.') }}
                </small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Shop') }}</th>
                                <th class="text-center">{{ __('All sale messages') }}</th>
                                @foreach ($saleNotifyChannels as $meta)
                                    <th class="text-center">
                                        {{ $meta['label'] }}
                                        <div class="small fw-normal text-muted">{{ $meta['hint'] }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($automationShops as $automationShop)
                                @php $master = $automationShop->notifiesOnSale(); @endphp
                                <tr>
                                    <td class="fw-medium">
                                        {{ $automationShop->name }}
                                        @unless ($master)
                                            <div class="small text-muted">{{ __('All messages off') }}</div>
                                        @endunless
                                    </td>
                                    <td class="text-center">
                                        <x-sale-notify-switch :shop="$automationShop"
                                            :channel="App\Models\Shop::SALE_NOTIFY_MASTER" :enabled="$master"
                                            :label="__('All sale messages from :shop', ['shop' => $automationShop->name])" />
                                    </td>
                                    @foreach ($saleNotifyChannels as $channel => $meta)
                                        <td class="text-center">
                                            <x-sale-notify-switch :shop="$automationShop" :channel="$channel"
                                                :enabled="$automationShop->saleNotificationEnabled($channel)"
                                                :disabled="!$master"
                                                :label="__(':setting from :shop', ['setting' => $meta['label'], 'shop' => $automationShop->name])" />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Baileys WhatsApp Sessions</h4>
                <small class="text-muted">Unofficial WhatsApp via @whiskeysockets/baileys — separate from the official
                    channel.</small>
            </div>
            @can('baileys.manage')
                <a href="{{ route('baileys.sessions.create') }}" class="btn btn-primary btn-sm">
                    <iconify-icon icon="solar:qr-code-bold-duotone" class="align-middle me-1"></iconify-icon>
                    New Session
                </a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="shop_id" class="form-select">
                        <option value="">All Shops</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected((string) request('shop_id') === (string) $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-secondary">Filter</button></div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Shop</th>
                            <th>Name</th>
                            <th>Number</th>
                            <th>Status</th>
                            <th>Connected</th>
                            <th>Created By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td>{{ $session->shop?->name }}</td>
                                <td>{{ $session->name }}</td>
                                <td>
                                    {{ $session->phone_number ?? '—' }}
                                    @if ($session->display_name)
                                        <div class="small text-muted">{{ $session->display_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span
                                        class="badge bg-{{ $session->status->color() }}">{{ $session->status->label() }}</span>
                                </td>
                                <td>{{ $session->connected_at?->diffForHumans() ?? '—' }}</td>
                                <td>{{ $session->creator?->name }}</td>
                                <td class="text-end">
                                    <a href="{{ route('baileys.sessions.show', $session) }}"
                                        class="btn btn-sm btn-outline-primary">View</a>
                                    @can('baileys.manage')
                                        @php
                                            $isDisconnected = in_array(
                                                $session->status->value,
                                                ['disconnected', 'failed'],
                                                true,
                                            );
                                        @endphp
                                        <form method="POST" action="{{ route('baileys.sessions.destroy', $session) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('{{ $isDisconnected ? 'Permanently delete this session and its chat history?' : 'Disconnect this session?' }}');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                {{ $isDisconnected ? 'Delete' : 'Disconnect' }}
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No sessions yet. Start one to scan a
                                    QR code.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $sessions->links() }}</div>
        </div>
    </div>
@endsection
