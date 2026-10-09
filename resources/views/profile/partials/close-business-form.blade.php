{{--
    Business owner only. Closing deletes everything the business holds, so it
    takes two steps: download an export, then close. The business is frozen
    for the grace period (only the owner can sign in, to cancel or download the
    final export) and deleted after it.
--}}
@php($closingErrors = $errors->businessClosure)

<x-ui-card :title="__('Close business')" class="mb-3 border-danger-subtle">
    <p class="text-muted">
        {{ __('Closing :business permanently deletes all of its data, including your own account. It happens in two steps: download an export of your data, then close the business. For :days days after that only you can sign in, and you can still change your mind; then everything is deleted.', ['business' => $business->name, 'days' => \App\Models\Business::GRACE_DAYS]) }}
    </p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        @foreach ([
            'shops' => __('Shops'),
            'staff' => __('Staff accounts'),
            'products' => __('Products'),
            'customers' => __('Customers'),
            'sales' => __('Sales'),
        ] as $key => $label)
            <x-ui-badge variant="danger" soft class="fs-13 fw-medium">
                {{ number_format($recordCounts[$key] ?? 0) }} {{ $label }}
            </x-ui-badge>
        @endforeach
    </div>

    {{-- Step 1: export --}}
    <div class="border rounded p-3 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <h5 class="mb-1">{{ __('1. Download your data') }}</h5>
                <p class="text-muted mb-0">
                    {{ __('A ZIP of spreadsheets (CSV) with your shops, products, stock, customers, sales, expenses, suppliers and staff. We email you a link when it is ready.') }}
                </p>
            </div>

            @if ($latestExport)
                <x-ui-badge :variant="$latestExport->isReady() || $latestExport->isPending() ? $latestExport->status->color() : 'secondary'">
                    {{ $latestExport->isReady() || $latestExport->isPending() ? __($latestExport->status->label()) : __('Expired') }}
                </x-ui-badge>
            @endif
        </div>

        @if ($latestExport?->isPending())
            <x-ui-alert variant="info" icon="solar:hourglass-line-broken" class="mt-3 mb-0">
                {{ __('Your export is being prepared (requested :time). We will email :email when it is ready.', ['time' => $latestExport->created_at->diffForHumans(), 'email' => $user->email]) }}
            </x-ui-alert>
        @elseif ($latestExport?->isReady())
            <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                <a href="{{ route('business.exports.download', $latestExport) }}" class="btn btn-primary">
                    <iconify-icon icon="solar:download-minimalistic-broken" class="align-middle me-1"></iconify-icon>
                    {{ __('Download export') }}
                </a>
                <span class="text-muted">
                    @if ($latestExport->wasDownloaded())
                        <iconify-icon icon="solar:check-circle-broken" class="align-middle text-success me-1"></iconify-icon>
                        {{ __('Downloaded :time.', ['time' => $latestExport->downloaded_at->diffForHumans()]) }}
                    @endif
                    {{ __('Available until :date.', ['date' => $latestExport->expires_at?->toFormattedDayDateString()]) }}
                </span>
            </div>
        @else
            @if ($latestExport?->status === \App\Enums\BusinessExportStatus::FAILED)
                <x-ui-alert variant="danger" icon="solar:danger-circle-broken" class="mt-3">
                    {{ __('The last export could not be prepared. Please try again.') }}
                </x-ui-alert>
            @endif

            <form method="POST" action="{{ route('business.exports.store') }}" class="mt-3">
                @csrf
                <x-ui-button type="submit" icon="solar:archive-down-minimlistic-broken">{{ __('Prepare export') }}</x-ui-button>
            </form>
        @endif
    </div>

    {{-- Step 2: close --}}
    <div class="border rounded p-3">
        <h5 class="mb-1">{{ __('2. Close the business') }}</h5>
        <p class="text-muted mb-3">
            @if ($closingExport)
                {{ __('Your staff will be signed out straight away. All the data will be deleted :days days later.', ['days' => \App\Models\Business::GRACE_DAYS]) }}
            @else
                {{ __('Available once you have downloaded an export from the last :days days.', ['days' => \App\Models\Business::EXPORT_VALID_DAYS]) }}
            @endif
        </p>

        <x-ui-button variant="danger" icon="solar:shop-2-broken" data-bs-toggle="modal"
            data-bs-target="#closeBusinessModal" :disabled="! $closingExport">
            {{ __('Close business') }}
        </x-ui-button>
    </div>
</x-ui-card>

@if ($closingExport)
    @push('modals')
        <x-ui-modal id="closeBusinessModal" :title="__('Close :business?', ['business' => $business->name])" centered>
            <x-ui-alert variant="danger" icon="solar:danger-triangle-broken">
                {{ __('On :date, every shop, product, customer, sale and staff account of :business, and your own account, will be permanently deleted. This cannot be undone after that date.', [
                    'date' => now()->addDays(\App\Models\Business::GRACE_DAYS)->toFormattedDayDateString(),
                    'business' => $business->name,
                ]) }}
            </x-ui-alert>

            @if ($closureCodePending)
                {{-- Step 2: the emailed code --}}
                <form id="closeBusinessForm" method="POST" action="{{ route('business.close') }}">
                    @csrf

                    <p>
                        {{ __('We have emailed a :length-digit code to :email. Enter it to close the business. It works for :minutes minutes.', [
                            'length' => \App\Support\BusinessClosureCode::LENGTH,
                            'email' => $user->email,
                            'minutes' => \App\Support\BusinessClosureCode::MINUTES,
                        ]) }}
                    </p>

                    <x-ui-form-input name="code" id="close_business_code" :label="__('Code')" error-bag="businessClosure"
                        class="fs-18 text-center" inputmode="numeric" autocomplete="one-time-code"
                        maxlength="{{ \App\Support\BusinessClosureCode::LENGTH }}" required autofocus />
                </form>

                <div class="d-flex flex-wrap gap-3 small">
                    <form method="POST" action="{{ route('business.close.resend') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0">{{ __('Send a new code') }}</button>
                    </form>
                    <form method="POST" action="{{ route('business.close.restart') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 text-muted">{{ __('Start again') }}</button>
                    </form>
                </div>
            @else
                {{-- Step 1: who is asking --}}
                <form id="closeBusinessForm" method="POST" action="{{ route('business.close.code') }}">
                    @csrf
                    @honeypot

                    <p class="text-muted">
                        {{ __('You may have to keep sales and tax records for several years (in Kenya, 5 years for VAT records). Your export is your copy.') }}
                    </p>

                    <x-ui-form-input name="email" id="close_business_email" type="email" :label="__('Your email address')"
                        error-bag="businessClosure" autocomplete="username" required />

                    <x-ui-form-input name="password" id="close_business_password" type="password" :label="__('Your password')"
                        error-bag="businessClosure" autocomplete="current-password" required />

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input @if ($closingErrors->has('export_kept')) is-invalid @endif"
                            id="close_business_export_kept" name="export_kept" value="1" required>
                        <label class="form-check-label" for="close_business_export_kept">
                            {{ __('I have downloaded my export and stored it safely.') }}
                        </label>
                        @if ($closingErrors->has('export_kept'))
                            <div class="invalid-feedback">{{ $closingErrors->first('export_kept') }}</div>
                        @endif
                    </div>
                </form>
            @endif

            <x-slot:footer>
                <x-ui-button variant="light" data-bs-dismiss="modal">{{ __('Cancel') }}</x-ui-button>
                @if ($closureCodePending)
                    <x-ui-button type="submit" variant="danger" form="closeBusinessForm">{{ __('Close business') }}</x-ui-button>
                @else
                    <x-ui-button type="submit" variant="danger" form="closeBusinessForm" icon="solar:letter-broken">{{ __('Email me a code') }}</x-ui-button>
                @endif
            </x-slot:footer>
        </x-ui-modal>
    @endpush

    {{-- Back on the page after a step: carry on in the pop-up --}}
    @if ($closingErrors->isNotEmpty() || session('closure-code-sent'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('closeBusinessModal')).show();
                });
            </script>
        @endpush
    @endif
@elseif ($closingErrors->has('export_kept'))
    <x-ui-alert variant="danger">{{ $closingErrors->first('export_kept') }}</x-ui-alert>
@endif
