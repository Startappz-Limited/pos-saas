{{--
    Shown while the signed-in owner's email is unverified and still inside its
    24 hours (Phase 6): where the link went, how long is left, and what to do
    about it. Placed in the page's content area (below the topbar, beside the
    sidebar), so the fixed sidebar never covers it.
--}}
@auth
    @php
        $verifyingUser = auth()->user();
        $hoursLeft = $verifyingUser->verificationHoursLeft();
    @endphp
    @if ($hoursLeft !== null && ! $verifyingUser->mustVerifyEmailNow())
        <div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-center gap-3 mb-3" role="status"
            aria-live="polite" id="verification-banner">
            <iconify-icon icon="solar:letter-bold-duotone" class="fs-1 flex-shrink-0" aria-hidden="true"></iconify-icon>

            <div class="flex-grow-1">
                <h5 class="alert-heading fs-15 mb-1">{{ __('Confirm your email address') }}</h5>
                <p class="mb-0">
                    {{ trans_choice(
                        '{0,1} We sent a link to :email. Confirm it within the hour to keep using your account.|[2,*] We sent a link to :email. Confirm it within :count hours to keep using your account.',
                        $hoursLeft,
                        ['email' => $verifyingUser->email, 'count' => $hoursLeft],
                    ) }}
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="mb-0 mt-1 fw-semibold">{{ __('A new link is on its way. Check your inbox and spam folder.') }}</p>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                <form method="POST" action="{{ route('verification.send') }}" class="m-0">
                    @csrf
                    @honeypot
                    <button type="submit" class="btn btn-sm btn-dark">{{ __('Resend email') }}</button>
                </form>
                <a href="{{ route('verification.notice') }}" class="btn btn-sm btn-outline-dark">{{ __('Change email') }}</a>
            </div>
        </div>
    @endif
@endauth
