@extends('layouts.app')

@section('title', $alert->title)

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <iconify-icon icon="{{ $alert->type->icon() }}" class="fs-3" aria-hidden="true"></iconify-icon>
                        <h5 class="card-title mb-0">{{ $alert->title }}</h5>
                    </div>

                    <span class="badge bg-{{ $alert->severity->color() }}">{{ $alert->severity->label() }}</span>
                </div>

                <div class="card-body">
                    <p class="mb-4">{{ $alert->message }}</p>

                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('Type') }}</dt>
                        <dd class="col-sm-9">{{ $alert->type->label() }}</dd>

                        <dt class="col-sm-3">{{ __('Category') }}</dt>
                        <dd class="col-sm-9">{{ $alert->category->label() }}</dd>

                        <dt class="col-sm-3">{{ __('Shop') }}</dt>
                        <dd class="col-sm-9">{{ $alert->shop?->name ?? __('System-wide') }}</dd>

                        <dt class="col-sm-3">{{ __('Raised by') }}</dt>
                        <dd class="col-sm-9">{{ $alert->creator?->name ?? __('System') }}</dd>

                        <dt class="col-sm-3">{{ __('Raised at') }}</dt>
                        <dd class="col-sm-9">{{ $alert->created_at?->toDayDateTimeString() }}</dd>

                        @if ($alert->scheduled_at)
                            <dt class="col-sm-3">{{ __('Scheduled for') }}</dt>
                            <dd class="col-sm-9">
                                {{ $alert->scheduled_at->toDayDateTimeString() }}
                                @if ($alert->isOverdue())
                                    <span class="badge bg-danger ms-1">{{ __('Overdue') }}</span>
                                @endif
                            </dd>
                        @endif

                        @if ($alert->expires_at)
                            <dt class="col-sm-3">{{ __('Expires') }}</dt>
                            <dd class="col-sm-9">{{ $alert->expires_at->toDayDateTimeString() }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($alert->is_resolved)
                <div class="card border-success">
                    <div class="card-header">
                        <h6 class="card-title mb-0">{{ __('Resolution') }}</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            {{ $alert->resolution_notes ?: __('Resolved with no notes.') }}
                        </p>
                        <p class="text-muted small mb-0">
                            {{ __('Resolved by :name on :date', [
                                'name' => $alert->resolver?->name ?? __('System'),
                                'date' => $alert->resolved_at?->toDayDateTimeString(),
                            ]) }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">{{ __('Status') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex gap-2">
                        @if ($alert->is_resolved)
                            <span class="badge bg-success">{{ __('Resolved') }}</span>
                        @else
                            <span class="badge bg-warning">{{ __('Open') }}</span>
                        @endif

                        @if ($alert->is_read)
                            <span class="badge bg-secondary">{{ __('Read') }}</span>
                        @else
                            <span class="badge bg-info">{{ __('Unread') }}</span>
                        @endif
                    </div>

                    @can('update', $alert)
                        @unless ($alert->is_read)
                            <form method="POST" action="{{ route('alerts.markRead', $alert) }}">
                                @csrf
                                @honeypot
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    {{ __('Mark as read') }}
                                </button>
                            </form>
                        @endunless

                        @unless ($alert->is_resolved)
                            <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                                @csrf
                                @honeypot

                                <div class="mb-2">
                                    <label for="resolution_notes" class="form-label">{{ __('Resolution notes') }}</label>
                                    <textarea class="form-control @error('resolution_notes') is-invalid @enderror" id="resolution_notes"
                                        name="resolution_notes" rows="3"
                                        placeholder="{{ __('Optional') }}">{{ old('resolution_notes') }}</textarea>
                                    @error('resolution_notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-primary w-100">{{ __('Resolve alert') }}</button>
                            </form>
                        @endunless
                    @endcan

                    <a href="{{ route('alerts.index') }}" class="btn btn-link px-0">{{ __('Back to alerts') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
