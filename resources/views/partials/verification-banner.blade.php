{{--
    Shown on every page while the signed-in owner's email is unverified and
    still inside its 24 hours (Phase 6): how long is left, and a way to resend.
--}}
@auth
    @php($hoursLeft = auth()->user()->verificationHoursLeft())
    @if ($hoursLeft !== null && ! auth()->user()->mustVerifyEmailNow())
        <div class="alert alert-warning rounded-0 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-3"
            role="status" id="verification-banner">
            <span>
                <iconify-icon icon="solar:letter-bold-duotone" class="align-middle me-1"></iconify-icon>
                {{ trans_choice('Verify your email — :count hour left|Verify your email — :count hours left', $hoursLeft, ['count' => $hoursLeft]) }}
            </span>
            <form method="POST" action="{{ route('verification.send') }}" class="m-0">
                @csrf
                @honeypot
                <button type="submit" class="btn btn-sm btn-dark">{{ __('Resend') }}</button>
            </form>
        </div>
    @endif
@endauth
