{{--
    The only page a business owner can reach while their business is closing
    (EnsureBusinessIsOpen): download the final export, or cancel the closure.
--}}
<x-guest-layout>
    <div class="text-center text-md-start">
        <div class="avatar-md bg-danger-subtle rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
            <i class="bx bx-store-alt fs-1 text-danger" aria-hidden="true"></i>
        </div>

        <h2 class="fw-bold fs-24 mb-2">{{ __(':business is closing', ['business' => $business->name]) }}</h2>

        @if (session('success'))
            <x-ui-alert variant="success" icon="solar:check-circle-broken" dismissible>{{ session('success') }}</x-ui-alert>
        @endif

        <p class="text-muted">
            {{ __('All of its data, including your account, will be permanently deleted on :date (:relative). Until then only you can sign in; your staff cannot use the system.', [
                'date' => $business->purge_after->toFormattedDayDateString(),
                'relative' => $business->purge_after->diffForHumans(),
            ]) }}
        </p>

        <x-ui-card :title="__('Final export')" class="text-start">
            <p class="text-muted">{{ __('Taken when you asked to close the business, so it holds everything up to that moment.') }}</p>

            @if ($finalExport?->isReady())
                <a href="{{ route('business.exports.download', $finalExport) }}" class="btn btn-primary">
                    <iconify-icon icon="solar:download-minimalistic-broken" class="align-middle me-1"></iconify-icon>
                    {{ __('Download final export') }}
                </a>
                @if ($finalExport->wasDownloaded())
                    <span class="text-muted ms-2">{{ __('Downloaded :time.', ['time' => $finalExport->downloaded_at->diffForHumans()]) }}</span>
                @endif
            @elseif ($finalExport?->isPending())
                <x-ui-alert variant="info" icon="solar:hourglass-line-broken" class="mb-0">
                    {{ __('Being prepared. We will email :email when it is ready; refresh this page to check.', ['email' => auth()->user()->email]) }}
                </x-ui-alert>
            @else
                <x-ui-alert variant="warning" icon="solar:danger-triangle-broken" class="mb-0">
                    {{ __('The final export could not be prepared. Your earlier export is still your copy; contact support if you need a new one.') }}
                </x-ui-alert>
            @endif
        </x-ui-card>

        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
            <form method="POST" action="{{ route('business.closing.cancel') }}" class="d-inline">
                @csrf
                <x-ui-button type="submit" variant="success" icon="solar:restart-broken">
                    {{ __('Cancel closure and reopen :business', ['business' => $business->name]) }}
                </x-ui-button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bx bx-log-out me-1" aria-hidden="true"></i>{{ __('Sign out') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
