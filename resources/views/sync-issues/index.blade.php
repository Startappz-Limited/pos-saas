@extends('layouts.app')

@section('title', __('Till sync review'))

@section('content')
    <x-ui-card>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="text-muted mb-0">
                {{ __('What the tills reported when they synced: invoice numbers that are missing or used twice, customers that may be the same person, and sales that need a second look. Nothing here stopped a sale.') }}
            </p>
            <div class="btn-group" role="group" aria-label="{{ __('Show') }}">
                <a href="{{ route('sync-issues.index') }}" class="btn btn-sm {{ $showResolved ? 'btn-outline-primary' : 'btn-primary' }}">
                    {{ __('To review') }} <span class="badge bg-light text-dark ms-1">{{ $openCount }}</span>
                </a>
                <a href="{{ route('sync-issues.index', ['show' => 'resolved']) }}" class="btn btn-sm {{ $showResolved ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('Resolved') }}</a>
            </div>
        </div>

        @if ($issues->isEmpty())
            <p class="text-center text-muted py-4 mb-0">{{ $showResolved ? __('Nothing resolved yet.') : __('Nothing to review. The tills have reported nothing unusual.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Issue') }}</th>
                            <th scope="col">{{ __('Till') }}</th>
                            <th scope="col">{{ __('Details') }}</th>
                            <th scope="col">{{ __('Found') }}</th>
                            <th scope="col" class="text-end">{{ $showResolved ? __('Resolved') : __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($issues as $issue)
                            @php($details = $issue->details ?? [])
                            @php($new = $customers->get($details['customer'] ?? ''))
                            @php($old = $customers->get($details['duplicate_of'] ?? ''))
                            <tr>
                                <td><x-ui-badge :variant="$issue->badgeVariant()">{{ $issue->label() }}</x-ui-badge></td>
                                <td class="text-nowrap small">
                                    {{ $issue->register?->name ?? '—' }} @if ($issue->register)<span class="text-muted">({{ $issue->register->code }})</span>@endif
                                    <div class="text-muted">{{ $issue->shop?->name }}</div>
                                </td>
                                <td class="small">
                                    @switch($issue->type)
                                        @case(\App\Models\SyncIssue::INVOICE_GAP)
                                            {{ __('Numbers :from to :to of :prefix… never reached the server.', ['from' => $details['from'] ?? '?', 'to' => $details['to'] ?? '?', 'prefix' => $details['prefix'] ?? '']) }}
                                            @if (is_array($details['missing'] ?? null))
                                                <div class="text-muted">{{ trans_choice('{1} 1 still missing.|[2,*] :count still missing.', count($details['missing']), ['count' => count($details['missing'])]) }}</div>
                                            @endif
                                            <div class="text-muted">{{ __('Check whether the till still holds unsynced sales, or whether numbers were skipped.') }}</div>
                                            @break
                                        @case(\App\Models\SyncIssue::INVOICE_REPEAT)
                                            {{ __(':number was sent again for a different sale, recorded :when. The second sale was not accepted and is still on the till.', ['number' => $details['invoice_number'] ?? '', 'when' => isset($details['recorded_at']) ? \Illuminate\Support\Carbon::parse($details['recorded_at'])->toDayDateTimeString() : '?']) }}
                                            @if ($existing = $sales->get($details['existing_sale'] ?? ''))
                                                <div><a href="{{ route('sales.show', $existing) }}">{{ __('See the sale that has it') }}</a></div>
                                            @endif
                                            @break
                                        @case(\App\Models\SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER)
                                            {{ __('Same phone (:phone):', ['phone' => $details['phone'] ?? '']) }}
                                            <ul class="mb-0 ps-3">
                                                <li>{{ $new?->name ?? __('(removed)') }} <span class="text-muted">{{ __('new from the till, :count sales', ['count' => $new?->sales_count ?? 0]) }}</span></li>
                                                <li>{{ $old?->name ?? __('(removed)') }} <span class="text-muted">{{ __('existing, :count sales', ['count' => $old?->sales_count ?? 0]) }}</span></li>
                                            </ul>
                                            @break
                                        @case(\App\Models\SyncIssue::NEGATIVE_STOCK)
                                            {{ __('A sale took more stock than the system had, so stock went below zero. Check the count, and any stock that arrived but wasn\'t received.') }}
                                            @if ($sale = $sales->get($details['sale'] ?? ''))
                                                <div><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></div>
                                            @endif
                                            @break
                                        @case(\App\Models\SyncIssue::IMPLAUSIBLE_TIME)
                                            {{ __('The till dated this sale :recorded, but it reached the server :received. The till\'s clock may be wrong.', [
                                                'recorded' => isset($details['recorded_at']) ? \Illuminate\Support\Carbon::parse($details['recorded_at'])->toDayDateTimeString() : '?',
                                                'received' => isset($details['received_at']) ? \Illuminate\Support\Carbon::parse($details['received_at'])->toDayDateTimeString() : '?',
                                            ]) }}
                                            @if ($sale = $sales->get($details['sale'] ?? ''))
                                                <div><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></div>
                                            @endif
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-nowrap small">{{ $issue->created_at->diffForHumans() }}</td>
                                <td class="text-end">
                                    @if ($showResolved)
                                        <span class="small">{{ __(ucfirst((string) $issue->resolution)) }}</span>
                                        <div class="text-muted small">{{ $issue->resolvedBy?->name ?? __('by the server') }}, {{ $issue->resolved_at?->diffForHumans() }}</div>
                                    @else
                                        @can('resolve', $issue)
                                            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                                                @if ($issue->type === \App\Models\SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER && $new && $old)
                                                    <form method="POST" action="{{ route('sync-issues.merge', $issue) }}"
                                                        onsubmit="return confirm(@js(__('Merge :new into :old? :new\'s sales and messages move to :old, and :new is removed from every till.', ['new' => $new->name, 'old' => $old->name])))">
                                                        @csrf
                                                        @honeypot
                                                        <x-ui-button variant="primary" size="sm" type="submit">{{ __('Merge into :name', ['name' => $old->name]) }}</x-ui-button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('sync-issues.resolve', $issue) }}">
                                                    @csrf
                                                    @honeypot
                                                    <x-ui-button variant="outline-secondary" size="sm" type="submit">
                                                        {{ $issue->type === \App\Models\SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER ? __('Keep both') : __('Mark as checked') }}
                                                    </x-ui-button>
                                                </form>
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $issues->links() }}</div>
        @endif
    </x-ui-card>
@endsection
