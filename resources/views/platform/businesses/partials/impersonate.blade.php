{{-- Start an impersonation of this member (Phase 5, B3). --}}
@unless ($member->isPlatformOperator() || ($member->business_status ?? 'active') !== 'active' || ! $business->isActive())
    <form method="POST" action="{{ route('platform.businesses.impersonate', [$business, $member->uuid]) }}" class="d-flex gap-1"
        data-swal-confirm data-swal-title="{{ __('Sign in as :name?', ['name' => $member->name]) }}"
        data-swal-text="{{ __('You will act with their permissions only, for :minutes minutes. Everything you do is recorded.', ['minutes' => config('platform.impersonation_minutes')]) }}"
        data-swal-button="{{ __('Sign in as them') }}">
        @csrf
        @honeypot
        <input type="text" name="reason" class="form-control form-control-sm" required maxlength="500"
            placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason for signing in as :name', ['name' => $member->name]) }}">
        <button type="submit" class="btn btn-sm btn-outline-dark text-nowrap">{{ __('Sign in as') }}</button>
    </form>
@endunless
