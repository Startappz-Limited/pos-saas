@extends('layouts.app')

@section('title', 'Baileys Session')

@push('styles')
    <style>
        .qr-wrap {
            max-width: 320px;
            margin: 0 auto;
        }
    </style>
@endpush

@if (!$session->isConnected())
    @push('scripts')
        <script>
            // Auto-refresh while waiting for QR scan / connection. Stops once connected.
            setTimeout(function() {
                window.location.reload();
            }, 5000);
        </script>
    @endpush
@endif

@section('content')
    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <h4 class="mb-0">{{ $session->name }}</h4>
                    <span class="badge bg-{{ $session->status->color() }}">{{ $session->status->label() }}</span>
                </div>
                <div class="card-body text-center">
                    @if ($session->isConnected())
                        <iconify-icon icon="solar:check-circle-bold-duotone"
                            style="font-size:96px;color:#10b981"></iconify-icon>
                        <h5 class="mt-2">Connected</h5>
                        <div class="text-muted">{{ $session->phone_number }}</div>
                        <div class="text-muted small">{{ $session->display_name }}</div>
                        <div class="small text-muted mt-2">JID: {{ $session->jid }}</div>
                    @elseif ($session->qr_code)
                        <div class="qr-wrap">
                            @if (str_starts_with($session->qr_code, 'data:image'))
                                <img src="{{ $session->qr_code }}" alt="WhatsApp QR" class="img-fluid border rounded p-2">
                            @else
                                {{-- Raw QR string: render as text the user can paste into a QR generator, or rely on the gateway to provide a data URI --}}
                                <pre class="text-start small">{{ $session->qr_code }}</pre>
                            @endif
                        </div>
                        <p class="text-muted small mt-2">
                            Open WhatsApp → Settings → Linked Devices → Link a device, then scan the QR.<br>
                            @if ($session->qr_expires_at)
                                Expires {{ $session->qr_expires_at->diffForHumans() }}.
                            @endif
                        </p>
                        <a href="{{ route('baileys.sessions.show', $session) }}"
                            class="btn btn-sm btn-outline-primary">Refresh</a>
                    @else
                        <div class="text-muted py-4">
                            <iconify-icon icon="solar:hourglass-bold-duotone" style="font-size:64px"></iconify-icon>
                            <div class="mt-2">{{ __('Waiting for a QR from the WhatsApp gateway…') }}</div>
                            <a href="{{ route('baileys.sessions.show', $session) }}"
                                class="btn btn-sm btn-outline-primary mt-2">Refresh</a>
                        </div>
                    @endif

                    @if ($session->last_error)
                        <div class="alert alert-danger mt-3 small text-start">{{ $session->last_error }}</div>
                    @endif
                </div>
                @can('baileys.manage')
                    @php
                        $isDisconnected = in_array($session->status->value, ['disconnected', 'failed'], true);
                    @endphp
                    <div class="card-footer text-end">
                        <form method="POST" action="{{ route('baileys.sessions.destroy', $session) }}"
                            onsubmit="return confirm('{{ $isDisconnected ? 'Permanently delete this session and its chat history?' : 'Disconnect this session?' }}');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">
                                {{ $isDisconnected ? 'Delete Session' : 'Disconnect' }}
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Shop</dt>
                        <dd class="col-sm-8">{{ $session->shop?->name }}</dd>
                        <dt class="col-sm-4">Created By</dt>
                        <dd class="col-sm-8">{{ $session->creator?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Session Key</dt>
                        <dd class="col-sm-8"><code>{{ $session->session_key }}</code></dd>
                        <dt class="col-sm-4">Last Seen</dt>
                        <dd class="col-sm-8">{{ $session->last_seen_at?->diffForHumans() ?? '—' }}</dd>
                        <dt class="col-sm-4">Connected At</dt>
                        <dd class="col-sm-8">{{ $session->connected_at?->toDayDateTimeString() ?? '—' }}</dd>
                    </dl>

                    @if (!empty($session->device_info))
                        <hr>
                        <h6>Device</h6>
                        <pre class="small bg-light p-2 rounded">{{ json_encode($session->device_info, JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
