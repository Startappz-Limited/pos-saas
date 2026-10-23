{{--
    Shown on every page while a platform operator acts as this user (Phase 5,
    B3): who, in which business, until when, and a way out.
--}}
@if (\App\Support\Platform\Impersonation::active() && auth()->check())
    @php
        $impersonationEnds = \Illuminate\Support\Carbon::createFromTimestamp((int) session(\App\Support\Platform\Impersonation::EXPIRES_KEY));
        $impersonatedBusiness = app(\App\Support\Tenancy\TenantContext::class)->business();
    @endphp
    <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"
        role="status" id="impersonation-banner">
        <span>
            <iconify-icon icon="solar:incognito-bold-duotone" class="align-middle me-1" aria-hidden="true"></iconify-icon>
            {{ __('Platform operator: you are signed in as :name in :business until :time. Everything you do is recorded.', [
                'name' => auth()->user()->name,
                'business' => $impersonatedBusiness?->name ?? '—',
                'time' => $impersonationEnds->format('H:i'),
            ]) }}
        </span>
        <form method="POST" action="{{ route('impersonation.end') }}" class="m-0">
            @csrf
            @honeypot
            <button type="submit" class="btn btn-sm btn-dark">{{ __('End impersonation') }}</button>
        </form>
    </div>
@endif
