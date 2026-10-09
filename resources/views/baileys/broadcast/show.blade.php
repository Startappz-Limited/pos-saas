@extends('layouts.app')

@section('title', 'Baileys Broadcast')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <h4 class="mb-0">{{ __('Post to Groups & Status') }}</h4>
            <form method="GET">
                <select name="session" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach ($sessions as $s)
                        <option value="{{ $s->uuid }}" @selected($session && $s->id === $session->id)>
                            {{ $s->shop?->name }} — {{ $s->phone_number ?? $s->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    @if (!$session)
        <div class="alert alert-info">No connected sessions. <a href="{{ route('baileys.sessions.create') }}">Start one</a>.
        </div>
    @else
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Post to Group') }}</h5>
                    </div>
                    <div class="card-body">
                        @if ($groupsError)
                            <div class="alert alert-warning small">
                                {{ __('Could not load groups from the WhatsApp gateway: :reason', ['reason' => $groupsError]) }}
                            </div>
                        @endif
                        <form method="POST" action="{{ route('baileys.broadcast.group', $session) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">{{ __('Group') }}</label>
                                <select name="jid" class="form-select" required>
                                    <option value="">{{ __('Select group') }}</option>
                                    @foreach ($groups as $g)
                                        <option value="{{ $g['jid'] ?? '' }}">{{ $g['name'] ?? ($g['jid'] ?? '') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">{{ __('Message') }}</label>
                                <textarea name="text" class="form-control" rows="4" maxlength="4096" required></textarea>
                            </div>
                            <button class="btn btn-primary w-100">{{ __('Post') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Update Status') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            @if ($statusAudience > 0)
                                {{ __('Goes to your :count most recent WhatsApp contacts. Those who have this number saved will see it.', ['count' => $statusAudience]) }}
                            @else
                                {{ __('No one to show a status to yet: statuses go to the people this number has chatted with.') }}
                            @endif
                        </p>
                        <form method="POST" action="{{ route('baileys.broadcast.status', $session) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select name="type" class="form-select" required>
                                    <option value="text">{{ __('Text') }}</option>
                                    <option value="image">{{ __('Image URL') }}</option>
                                    <option value="video">{{ __('Video URL') }}</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">{{ __('URL (image/video)') }}</label>
                                <input name="url" class="form-control" placeholder="https://...">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">{{ __('Text / Caption') }}</label>
                                <textarea name="text" class="form-control" rows="3" maxlength="1000"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">{{ __('Background (text status)') }}</label>
                                <input type="color" name="background_color" class="form-control form-control-color" value="#075E54">
                            </div>
                            <button class="btn btn-primary w-100" @disabled($statusAudience === 0)>{{ __('Post Status') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
