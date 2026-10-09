@extends('layouts.app')

@section('title', 'Baileys Inbox')

@push('styles')
    <style>
        .baileys-chat-list {
            max-height: calc(100vh - 320px);
            overflow-y: auto;
        }

        .baileys-chatbox {
            max-height: calc(100vh - 260px);
            overflow-y: auto;
        }

        .baileys-chat-list a.active-chat {
            background-color: rgba(var(--bs-primary-rgb), .08);
            border-radius: .25rem;
        }

        .chat-conversation-list .chat-ctext-wrap p {
            white-space: pre-wrap;
            word-break: break-word;
        }

        .baileys-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary);
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }

        .baileys-avatar.lg {
            width: 40px;
            height: 40px;
            font-size: 14px;
        }
    </style>
@endpush

@php
    $initials = function (?string $label, ?string $jid) {
        $source = trim((string) ($label ?: $jid ?? '?'));
        if ($source === '') {
            return '?';
        }
        $parts = preg_split('/[\s@\-+]+/', $source) ?: [];
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        if (count($parts) === 0) {
            return mb_strtoupper(mb_substr($source, 0, 2));
        }
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    };
@endphp

@section('content')
    <div class="row g-2">
        {{-- ======================== LEFT PANE — Sessions + Chats ======================== --}}
        <div class="col-xxl-4 col-lg-5">
            <div class="card position-relative overflow-hidden">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <iconify-icon icon="solar:chat-round-dots-bold-duotone"
                            class="align-middle me-1 text-primary"></iconify-icon>
                        Baileys Inbox
                    </h4>
                    <a href="{{ route('baileys.sessions.index') }}" class="btn btn-sm btn-light" title="Manage sessions">
                        <i class="bx bx-cog fs-18"></i>
                    </a>
                </div>

                <form method="GET" class="px-3 pb-2 d-flex gap-2">
                    <select name="session" class="form-select form-select-sm" onchange="this.form.submit()">
                        @forelse ($sessions as $s)
                            <option value="{{ $s->uuid }}" @selected($session && $s->id === $session->id)>
                                {{ $s->shop?->name }} — {{ $s->phone_number ?? $s->name }}
                            </option>
                        @empty
                            <option>No connected sessions</option>
                        @endforelse
                    </select>
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()"
                        style="max-width:140px">
                        <option value="">All</option>
                        @foreach ($chatTypes as $t)
                            <option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </form>

                <form method="GET" class="chat-search px-3">
                    @if ($session)
                        <input type="hidden" name="session" value="{{ $session->uuid }}">
                    @endif
                    @if (request('type'))
                        <input type="hidden" name="type" value="{{ request('type') }}">
                    @endif
                    <div class="chat-search-box">
                        <input class="form-control form-control-sm" type="text" name="q"
                            value="{{ request('q') }}" placeholder="Search chats…">
                        <button type="submit" class="btn btn-sm btn-link search-icon p-0"><i
                                class="bx bx-search-alt"></i></button>
                    </div>
                </form>

                @if ($session)
                    @can('baileys.send')
                        <div class="px-3 pt-2">
                            <details>
                                <summary class="small text-primary mb-2" style="cursor:pointer">
                                    <i class="bx bx-plus-circle"></i> Start new conversation
                                </summary>
                                <form method="POST" action="{{ route('baileys.inbox.start', $session) }}" class="row g-1 mt-1">
                                    @csrf
                                    <div class="col-12">
                                        <input name="phone"
                                            class="form-control form-control-sm @error('phone') is-invalid @enderror"
                                            value="{{ old('phone') }}" placeholder="Phone (e.g. 254712345678)" required
                                            maxlength="32">
                                        @error('phone')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-8">
                                        <input name="name" class="form-control form-control-sm" value="{{ old('name') }}"
                                            placeholder="Name (optional)">
                                    </div>
                                    <div class="col-4">
                                        <button class="btn btn-primary btn-sm w-100">Open</button>
                                    </div>
                                </form>
                            </details>
                        </div>
                    @endcan
                @endif

                <ul class="nav nav-tabs nav-justified nav-bordered border-top mt-2">
                    <li class="nav-item">
                        <a href="#chat-list" data-bs-toggle="tab" class="nav-link active py-2">Chats</a>
                    </li>
                    <li class="nav-item">
                        <a href="#group-list" data-bs-toggle="tab" class="nav-link py-2">Groups</a>
                    </li>
                    <li class="nav-item">
                        <a href="#channel-list" data-bs-toggle="tab" class="nav-link py-2">Channels</a>
                    </li>
                </ul>

                <div class="tab-content">
                    @foreach ([['chat-list', 'private', 'No chats yet'], ['group-list', 'group', 'No groups'], ['channel-list', 'channel', 'No channels']] as [$paneId, $typeFilter, $emptyMsg])
                        <div class="tab-pane @if ($paneId === 'chat-list') show active @endif"
                            id="{{ $paneId }}">
                            <div class="px-3 mb-3 baileys-chat-list">
                                @if (!$session)
                                    <div class="p-4 text-center text-muted">
                                        No connected sessions. <a href="{{ route('baileys.sessions.create') }}">Start
                                            one</a>.
                                    </div>
                                @else
                                    @php
                                        $rows = $chats->filter(fn($c) => $c->type->value === $typeFilter);
                                    @endphp

                                    @forelse ($rows as $chat)
                                        @php
                                            $isActive = $activeChat && $activeChat->id === $chat->id;
                                            $label = $chat->name ?? ($chat->phone ? '+' . $chat->phone : $chat->jid);
                                            $url = route('baileys.inbox.index', [
                                                'session' => $session->uuid,
                                                'chat' => $chat->id,
                                                'type' => request('type'),
                                                'q' => request('q'),
                                            ]);
                                            $ini = $initials($label, $chat->jid);
                                        @endphp
                                        <a href="{{ $url }}" class="text-body d-block" data-chat-id="{{ $chat->id }}">
                                            <div
                                                class="d-flex align-items-center p-2 {{ $isActive ? 'active-chat' : '' }}">
                                                <div class="flex-shrink-0 position-relative me-2">
                                                    <span class="baileys-avatar">{{ $ini }}</span>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <h5 class="my-0 fs-14">
                                                        <span class="float-end text-muted fs-12" data-role="last-time">
                                                            {{ $chat->last_message_at?->diffForHumans(['short' => true]) }}
                                                        </span>
                                                        <span data-role="chat-label">{{ $label }}</span>
                                                    </h5>
                                                    <p
                                                        class="mt-1 mb-0 fs-13 text-muted d-flex align-items-end justify-content-between">
                                                        <span class="text-truncate" style="max-width:75%">
                                                            {{ $chat->last_message_preview ?: '—' }}
                                                        </span>
                                                        <span data-role="unread">
                                                            @if ($chat->unread_count > 0)
                                                                <span
                                                                    class="badge bg-danger rounded-pill">{{ $chat->unread_count }}</span>
                                                            @endif
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </a>
                                    @empty
                                        <div class="p-4 text-center text-muted small">{{ $emptyMsg }}.</div>
                                    @endforelse
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ======================== RIGHT PANE — Conversation ======================== --}}
        <div class="col-xxl-8 col-lg-7">
            <div class="card position-relative overflow-hidden">
                @if (!$session)
                    <div class="p-5 text-center text-muted">
                        <iconify-icon icon="solar:chat-square-arrow-bold-duotone" style="font-size:64px"></iconify-icon>
                        <p class="mt-2 mb-0">Connect a Baileys session to start chatting.</p>
                    </div>
                @elseif (!$activeChat)
                    <div class="p-5 text-center text-muted">
                        <iconify-icon icon="solar:chat-round-dots-bold-duotone" style="font-size:64px"></iconify-icon>
                        <p class="mt-2 mb-0">Pick a chat on the left to view messages.</p>
                    </div>
                @else
                    @php
                        $headerLabel =
                            $activeChat->name ?? ($activeChat->phone ? '+' . $activeChat->phone : $activeChat->jid);
                        $needsMapping = str_ends_with($activeChat->jid, '@lid') || !$activeChat->phone;
                    @endphp

                    <div class="card-header d-flex align-items-center mh-100">
                        <div class="d-flex align-items-center">
                            <span class="baileys-avatar lg me-2">{{ $initials($headerLabel, $activeChat->jid) }}</span>
                            <div class="d-flex flex-column">
                                <h5 class="my-0 fs-16 fw-semibold">{{ $headerLabel }}</h5>
                                <p class="mb-0 small text-muted">
                                    {{ $activeChat->jid }}
                                    @if ($activeChat->phone)
                                        · <span
                                            class="badge bg-success-subtle text-success">+{{ $activeChat->phone }}</span>
                                    @endif
                                    · <span
                                        class="badge bg-secondary-subtle text-secondary">{{ $activeChat->type->label() }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex-grow-1">
                            <ul class="list-inline float-end d-flex gap-3 mb-0">
                                @can('baileys.send')
                                    <li class="list-inline-item fs-20">
                                        <a href="javascript:void(0);" class="text-dark" data-bs-toggle="collapse"
                                            data-bs-target="#mapChatPanel" title="Map / rename">
                                            <i class="bx bx-user-pin"></i>
                                        </a>
                                    </li>
                                @endcan
                                <li class="list-inline-item fs-20 dropdown">
                                    <a href="javascript:void(0);" class="dropdown-toggle arrow-none text-dark"
                                        data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('baileys.sessions.show', $session) }}">
                                            <i class="bx bx-info-circle me-2"></i>Session details
                                        </a>
                                        <a class="dropdown-item"
                                            href="{{ route('baileys.inbox.index', ['session' => $session->uuid]) }}">
                                            <i class="bx bx-x me-2"></i>Close chat
                                        </a>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    @can('baileys.send')
                        <div id="mapChatPanel" class="collapse @if ($needsMapping && !$activeChat->phone) show @endif border-bottom">
                            <form method="POST" action="{{ route('baileys.inbox.map', $activeChat) }}"
                                class="row g-2 p-3 bg-light bg-opacity-50">
                                @csrf
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Phone</label>
                                    <input name="phone"
                                        class="form-control form-control-sm @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $activeChat->phone) }}" placeholder="e.g. 254712345678"
                                        required maxlength="32">
                                    @error('phone')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Display name</label>
                                    <input name="name" class="form-control form-control-sm"
                                        value="{{ old('name', $activeChat->name) }}" placeholder="Optional" maxlength="120">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn btn-primary btn-sm w-100">Save</button>
                                </div>
                            </form>
                        </div>
                    @endcan

                    <div class="chat-box">
                        <ul class="chat-conversation-list p-3 baileys-chatbox" id="baileys-chat-thread">
                            @forelse ($messages as $m)
                                @php $isOut = $m->direction->value === 'outbound'; @endphp
                                <li class="clearfix {{ $isOut ? 'odd' : '' }}" data-msg-id="{{ $m->id }}">
                                    <div class="chat-conversation-text {{ $isOut ? 'ms-0' : '' }}">
                                        <div class="d-flex {{ $isOut ? 'justify-content-end' : '' }}">
                                            <div class="chat-ctext-wrap">
                                                @if ($m->media_url)
                                                    <div class="mb-1">
                                                        @if (in_array($m->type->value, ['image', 'sticker']))
                                                            <a href="{{ $m->media_url }}" target="_blank">
                                                                <img src="{{ $m->media_url }}" alt="media"
                                                                    class="img-thumbnail" style="max-height:160px">
                                                            </a>
                                                        @else
                                                            <a href="{{ $m->media_url }}" target="_blank"
                                                                class="d-inline-flex align-items-center gap-1">
                                                                <i class="bx bx-paperclip"></i>
                                                                <span>{{ $m->media_filename ?? $m->type->label() }}</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if (!is_null($m->content) && $m->content !== '')
                                                    <p>{{ $m->content }}</p>
                                                @elseif (!$m->media_url)
                                                    <p class="text-muted fst-italic mb-0">[{{ $m->type->label() }}]</p>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-muted fs-12 mb-0 mt-1 {{ $isOut ? 'text-end' : 'ms-2' }}">
                                            {{ $m->sent_at?->format('H:i') ?? $m->created_at->format('H:i') }}
                                            @if ($isOut)
                                                <span data-role="tick">
                                                    @if ($m->status->value === 'read')
                                                        <i class="bx bx-check-double ms-1 text-primary" title="Read"></i>
                                                    @elseif ($m->status->value === 'delivered')
                                                        <i class="bx bx-check-double ms-1 text-muted"
                                                            title="Delivered"></i>
                                                    @elseif ($m->status->value === 'sent')
                                                        <i class="bx bx-check ms-1 text-muted" title="Sent"></i>
                                                    @elseif ($m->status->value === 'failed')
                                                        <i class="bx bx-error ms-1 text-danger" title="Failed"></i>
                                                    @else
                                                        <i class="bx bx-time ms-1 text-muted" title="Pending"></i>
                                                    @endif
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @empty
                                <li>
                                    <div class="text-center text-muted py-4">No messages yet.</div>
                                </li>
                            @endforelse
                        </ul>

                        @can('baileys.send')
                            <div class="bg-light bg-opacity-50 p-2 border-top">
                                <form id="baileys-send-form" method="POST"
                                    action="{{ route('baileys.inbox.send', $activeChat) }}"
                                    autocomplete="off">
                                    @csrf
                                    <div class="row align-items-center">
                                        <div class="col mb-2 mb-sm-0 d-flex">
                                            <div class="input-group">
                                                <span
                                                    class="btn btn-sm btn-light d-flex align-items-center input-group-text"><i
                                                        class="bx bx-smile fs-18"></i></span>
                                                <input type="text" name="text" class="form-control border-0"
                                                    placeholder="Type a message…" required maxlength="4096"
                                                    autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="col-sm-auto">
                                            <div class="btn-group btn-toolbar">
                                                <label class="btn btn-sm btn-light mb-0" title="Attach file"
                                                    for="baileys-file-input">
                                                    <i class="bx bx-paperclip fs-18"></i>
                                                </label>
                                                <input type="file" id="baileys-file-input" class="d-none"
                                                    accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.csv,.zip">
                                                <button type="submit" class="btn btn-sm btn-primary chat-send"><i
                                                        class="bx bx-send fs-18"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <form id="baileys-media-form" method="POST"
                                    action="{{ route('baileys.inbox.sendMedia', $activeChat) }}"
                                    enctype="multipart/form-data" class="d-none">
                                    @csrf
                                </form>
                            </div>

                            <!-- Media preview modal -->
                            <div class="modal fade" id="baileys-media-modal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header py-2">
                                            <h5 class="modal-title fs-15">Send attachment</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div id="baileys-media-preview"
                                                class="text-center mb-3 bg-light rounded p-2"
                                                style="min-height:160px;display:flex;align-items:center;justify-content:center">
                                            </div>
                                            <div class="text-muted small mb-2" id="baileys-media-meta"></div>
                                            <input type="text" id="baileys-media-caption" class="form-control"
                                                placeholder="Add a caption (optional)" maxlength="1024">
                                        </div>
                                        <div class="modal-footer py-2">
                                            <button type="button" class="btn btn-light btn-sm"
                                                data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary btn-sm"
                                                id="baileys-media-confirm">
                                                <i class="bx bx-send me-1"></i>Send
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const thread = document.getElementById('baileys-chat-thread');
            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }

            const escapeHtml = (s) => String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

            const tickHtml = (status) => {
                if (status === 'read')      return '<i class="bx bx-check-double ms-1 text-primary" title="Read"></i>';
                if (status === 'delivered') return '<i class="bx bx-check-double ms-1 text-muted" title="Delivered"></i>';
                if (status === 'sent')      return '<i class="bx bx-check ms-1 text-muted" title="Sent"></i>';
                if (status === 'failed')    return '<i class="bx bx-error ms-1 text-danger" title="Failed"></i>';
                return '<i class="bx bx-time ms-1 text-muted" title="Pending"></i>';
            };

            const updateMessageStatus = (id, status) => {
                const li = document.querySelector(`li[data-msg-id="${id}"]`);
                if (!li) return;
                const tick = li.querySelector('[data-role="tick"]');
                if (tick) tick.outerHTML = `<span data-role="tick">${tickHtml(status)}</span>`;
            };

            const renderMediaHtml = (m) => {
                if (!m.media_url) return '';
                if (m.type === 'image' || m.type === 'sticker') {
                    return `<div class="mb-1"><a href="${escapeHtml(m.media_url)}" target="_blank">
                        <img src="${escapeHtml(m.media_url)}" alt="media" class="img-thumbnail" style="max-height:160px"></a></div>`;
                }
                if (m.type === 'video') {
                    return `<div class="mb-1"><video src="${escapeHtml(m.media_url)}" controls
                        style="max-height:200px;max-width:100%"></video></div>`;
                }
                if (m.type === 'audio') {
                    return `<div class="mb-1"><audio src="${escapeHtml(m.media_url)}" controls></audio></div>`;
                }
                return `<div class="mb-1"><a href="${escapeHtml(m.media_url)}" target="_blank"
                    class="d-inline-flex align-items-center gap-1"><i class="bx bx-paperclip"></i>
                    <span>${escapeHtml(m.media_filename || m.type || 'file')}</span></a></div>`;
            };

            const appendOutbound = (m) => {
                if (!thread) return;
                const li = document.createElement('li');
                li.className = 'clearfix odd';
                li.dataset.msgId = m.id;
                const media = renderMediaHtml(m);
                const text = m.content ? `<p>${escapeHtml(m.content)}</p>` : '';
                li.innerHTML = `
                    <div class="chat-conversation-text ms-0">
                        <div class="d-flex justify-content-end">
                            <div class="chat-ctext-wrap">${media}${text}</div>
                        </div>
                        <p class="text-muted fs-12 mb-0 mt-1 text-end">
                            ${escapeHtml(m.sent_at)}
                            <span data-role="tick">${tickHtml(m.status)}</span>
                        </p>
                    </div>`;
                thread.appendChild(li);
                thread.scrollTop = thread.scrollHeight;
            };

            // ---- Seamless send (no full reload) ----
            const form = document.getElementById('baileys-send-form');
            if (form) {
                const input = form.querySelector('input[name="text"]');
                const btn = form.querySelector('button[type="submit"]');

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const text = input.value.trim();
                    if (!text) return;
                    btn.disabled = true;

                    const fd = new FormData(form);
                    try {
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd,
                            credentials: 'same-origin',
                        });
                        if (!res.ok) {
                            const err = await res.json().catch(() => ({}));
                            alert(err.message || 'Failed to send message.');
                            return;
                        }
                        const json = await res.json();
                        if (json.message) appendOutbound(json.message);
                        input.value = '';
                        input.focus();
                        document.dispatchEvent(new CustomEvent('baileys:sent'));
                    } catch (err) {
                        alert('Network error sending message.');
                    } finally {
                        btn.disabled = false;
                    }
                });
            }

            // ---- Media upload from composer ----
            const fileInput = document.getElementById('baileys-file-input');
            const mediaForm = document.getElementById('baileys-media-form');
            const mediaModalEl = document.getElementById('baileys-media-modal');
            if (fileInput && mediaForm && mediaModalEl && window.bootstrap) {
                const modal = new bootstrap.Modal(mediaModalEl);
                const previewEl = document.getElementById('baileys-media-preview');
                const metaEl = document.getElementById('baileys-media-meta');
                const captionEl = document.getElementById('baileys-media-caption');
                const confirmBtn = document.getElementById('baileys-media-confirm');
                let pendingFile = null;
                let previewUrl = null;

                const cleanup = () => {
                    if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
                    pendingFile = null;
                    fileInput.value = '';
                    captionEl.value = '';
                    previewEl.innerHTML = '';
                    metaEl.textContent = '';
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="bx bx-send me-1"></i>Send';
                };

                const formatBytes = (n) => {
                    if (n < 1024) return n + ' B';
                    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
                    return (n / (1024 * 1024)).toFixed(1) + ' MB';
                };

                fileInput.addEventListener('change', () => {
                    const file = fileInput.files && fileInput.files[0];
                    if (!file) return;
                    pendingFile = file;
                    previewUrl = URL.createObjectURL(file);
                    metaEl.textContent = `${file.name} · ${formatBytes(file.size)}`;
                    if (file.type.startsWith('image/')) {
                        previewEl.innerHTML = `<img src="${previewUrl}" alt="preview" style="max-height:240px;max-width:100%">`;
                    } else if (file.type.startsWith('video/')) {
                        previewEl.innerHTML = `<video src="${previewUrl}" controls style="max-height:240px;max-width:100%"></video>`;
                    } else if (file.type.startsWith('audio/')) {
                        previewEl.innerHTML = `<audio src="${previewUrl}" controls></audio>`;
                    } else {
                        previewEl.innerHTML = `<div class="text-muted"><i class="bx bx-file fs-1"></i><div>${escapeHtml(file.name)}</div></div>`;
                    }
                    modal.show();
                });

                mediaModalEl.addEventListener('hidden.bs.modal', () => {
                    if (!confirmBtn.disabled) cleanup();
                });

                confirmBtn.addEventListener('click', async () => {
                    if (!pendingFile) return;
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';

                    const fd = new FormData(mediaForm);
                    fd.append('file', pendingFile);
                    if (captionEl.value.trim()) fd.append('caption', captionEl.value.trim());

                    try {
                        const res = await fetch(mediaForm.action, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd,
                            credentials: 'same-origin',
                        });
                        if (!res.ok) {
                            const err = await res.json().catch(() => ({}));
                            const msg = err.message
                                || (err.errors && Object.values(err.errors).flat().join('\n'))
                                || 'Failed to upload media.';
                            alert(msg);
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = '<i class="bx bx-send me-1"></i>Send';
                            return;
                        }
                        const json = await res.json();
                        if (json.message) appendOutbound(json.message);
                        document.dispatchEvent(new CustomEvent('baileys:sent'));
                        modal.hide();
                        cleanup();
                    } catch (err) {
                        alert('Network error uploading media.');
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="bx bx-send me-1"></i>Send';
                    }
                });
            }

            // ---- Live updates via Server-Sent Events ----
            @if ($activeChat)
                const streamUrl = @json(route('baileys.inbox.stream', ['chat' => $activeChat->id]));
                const threadEl = document.getElementById('baileys-chat-thread');

                const appendInbound = (m) => {
                    if (threadEl.querySelector(`li[data-msg-id="${m.id}"]`)) return;
                    const li = document.createElement('li');
                    li.className = 'clearfix';
                    li.dataset.msgId = m.id;
                    const media = renderMediaHtml(m);
                    const text = m.content ? `<p>${escapeHtml(m.content)}</p>` : '';
                    li.innerHTML = `
                        <div class="chat-conversation-text">
                            <div class="chat-ctext-wrap">${media}${text}</div>
                            <p class="text-muted fs-12 mb-0 mt-1">${escapeHtml(m.sent_at)}</p>
                        </div>`;
                    threadEl.appendChild(li);
                    threadEl.scrollTop = threadEl.scrollHeight;
                };

                const upsertOutbound = (m) => {
                    if (threadEl.querySelector(`li[data-msg-id="${m.id}"]`)) return;
                    appendOutbound(m);
                };

                const updateChatRow = (c) => {
                    const anchor = document.querySelector(`a[data-chat-id="${c.id}"]`);
                    if (!anchor) return; // new chat: appears on next page navigation
                    const time = anchor.querySelector('[data-role="last-time"]');
                    if (time && c.last_message_human) time.textContent = c.last_message_human;
                    const unread = anchor.querySelector('[data-role="unread"]');
                    if (unread) {
                        unread.innerHTML = c.unread_count > 0
                            ? `<span class="badge bg-danger rounded-pill">${c.unread_count}</span>`
                            : '';
                    }
                    // Bubble row to the top of its list
                    const list = anchor.parentElement;
                    if (list && list.firstElementChild !== anchor) {
                        list.insertBefore(anchor, list.firstElementChild);
                    }
                };

                let es = null;
                let lastMessageId = (() => {
                    const items = threadEl.querySelectorAll('li[data-msg-id]');
                    return items.length ? parseInt(items[items.length - 1].dataset.msgId, 10) : 0;
                })();

                const connect = () => {
                    const url = `${streamUrl}?since_message_id=${lastMessageId}`;
                    es = new EventSource(url, { withCredentials: true });

                    es.addEventListener('messages', (e) => {
                        const msgs = JSON.parse(e.data);
                        for (const m of msgs) {
                            if (m.direction === 'outbound') upsertOutbound(m);
                            else appendInbound(m);
                            if (m.id > lastMessageId) lastMessageId = m.id;
                        }
                    });

                    es.addEventListener('chats', (e) => {
                        JSON.parse(e.data).forEach(updateChatRow);
                    });

                    es.addEventListener('status', (e) => {
                        const updates = JSON.parse(e.data);
                        for (const u of updates) updateMessageStatus(u.id, u.status);
                    });

                    es.onerror = () => {
                        // Browser auto-reconnects on its own (retry: 1500 in stream).
                    };
                };

                if (window.EventSource) {
                    connect();
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden && es) {
                            es.close();
                            es = null;
                        } else if (!document.hidden && !es) {
                            connect();
                        }
                    });
                }
            @endif
        });
    </script>
@endpush
