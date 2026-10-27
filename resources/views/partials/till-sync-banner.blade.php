{{--
    Owners see which paired tills haven't synced for a day (Phase 7, decision
    4.5): the till, its shop and when it last synced. Those tills keep selling;
    their sales and stock reach the server only once they connect.
--}}
@auth
    @php
        $overdueTills = current_business() !== null && auth()->user()->isBusinessOwner()
            ? \App\Models\Register::query()->syncOverdue()->with('shop:id,name')->orderBy('shop_id')->get()
            : collect();
    @endphp
    @if ($overdueTills->isNotEmpty())
        <div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-start gap-3 mb-3" role="status" id="till-sync-banner">
            <iconify-icon icon="solar:cloud-cross-bold-duotone" class="fs-1 flex-shrink-0" aria-hidden="true"></iconify-icon>
            <div class="flex-grow-1">
                <h5 class="alert-heading fs-15 mb-1">
                    {{ trans_choice('{1} A till hasn\'t synced for over :hours hours|[2,*] :count tills haven\'t synced for over :hours hours', $overdueTills->count(), ['count' => $overdueTills->count(), 'hours' => (int) config('sync.overdue_hours')]) }}
                </h5>
                <p class="mb-1">{{ __('They can keep selling, but their sales won\'t show in your reports, and their stock won\'t update, until they connect.') }}</p>
                <ul class="mb-0 ps-3">
                    @foreach ($overdueTills as $till)
                        <li>
                            <strong>{{ $till->name }}</strong> ({{ $till->code }}), {{ $till->shop?->name }}:
                            {{ $till->last_synced_at
                                ? __('last synced :when', ['when' => $till->last_synced_at->diffForHumans()])
                                : __('not synced since it was paired :when', ['when' => $till->paired_at?->diffForHumans()]) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endauth
