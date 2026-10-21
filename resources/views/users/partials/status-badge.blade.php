{{--
    A person's status (stage 3): their status in the current business (what a
    business suspends or deactivates), unless their account itself isn't
    active (the platform operator's), plus a marker until their email is
    verified. Expects $user.
--}}
@php
    $accountStatus = $user->status instanceof \App\Enums\UserStatus ? $user->status : \App\Enums\UserStatus::tryFrom((string) $user->status);
    $shownStatus = $accountStatus !== \App\Enums\UserStatus::ACTIVE && $accountStatus !== null
        ? $accountStatus
        : ($user->businessStatus() ?? $accountStatus ?? \App\Enums\UserStatus::ACTIVE);
@endphp
<x-ui-badge :variant="$shownStatus->color()">{{ __($shownStatus->label()) }}</x-ui-badge>
@if (! $user->hasVerifiedEmail())
    <x-ui-badge variant="warning" soft>{{ __('Email not verified') }}</x-ui-badge>
@endif
