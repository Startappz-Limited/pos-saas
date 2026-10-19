@extends('layouts.app')

@section('title', 'Data & closure')

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <x-ui-card title="{{ __('Export your data') }}">
                <p class="text-muted">
                    {{ __('A ZIP archive of everything this business holds: every record (one JSON file per table), its audit trail and its files. Owners get an email when it is ready.') }}
                </p>
                <form method="POST" action="{{ route('business.exports.store') }}" class="mb-3">
                    @csrf
                    <x-ui-button variant="primary" type="submit">{{ __('Prepare an export') }}</x-ui-button>
                </form>

                @if ($exports->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Requested') }}</th>
                                    <th>{{ __('By') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Download') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($exports as $export)
                                    <tr>
                                        <td>{{ $export->created_at->toDayDateTimeString() }}</td>
                                        <td>{{ $export->requester?->name ?? '—' }}</td>
                                        <td>
                                            @if ($export->isReady())
                                                <span class="badge bg-success-subtle text-success">{{ __('Ready') }}</span>
                                                <span class="small text-muted">{{ number_format(($export->size ?? 0) / 1048576, 1) }} MB</span>
                                            @elseif ($export->status === \App\Models\BusinessExport::STATUS_FAILED)
                                                <span class="badge bg-danger-subtle text-danger">{{ __('Failed') }}</span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning">{{ __('Preparing') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($export->isReady())
                                                <a class="btn btn-sm btn-light"
                                                    href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('business.exports.download', now()->addHour(), $export) }}">
                                                    {{ __('Download') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui-card>
        </div>

        <div class="col-lg-5">
            <x-ui-card title="{{ __('Close the business') }}">
                <p class="text-muted">
                    {{ __('Closing stops everyone from working in the business and emails its owners a final export. :days days later, all of its data, audit trail and files are erased for good. Until then, any owner can cancel the closure.', ['days' => $graceDays]) }}
                </p>

                @if ($pendingCode)
                    <form method="POST" action="{{ route('business.closure.confirm') }}">
                        @csrf
                        <x-ui-form-input name="code" label="{{ __('Confirmation code from your email') }}" inputmode="numeric"
                            autocomplete="one-time-code" maxlength="6" required />
                        <x-ui-button variant="danger" type="submit" class="w-100">{{ __('Close the business') }}</x-ui-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('business.closure.code') }}">
                        @csrf
                        <x-ui-button variant="outline-danger" type="submit" class="w-100">{{ __('Email me a confirmation code') }}</x-ui-button>
                    </form>
                @endif
            </x-ui-card>
        </div>
    </div>
@endsection
