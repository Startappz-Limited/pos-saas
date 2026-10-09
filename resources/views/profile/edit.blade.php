@extends('layouts.app')

@section('title', __('My Profile'))

@section('content')
    <div class="row">
        <!-- Account Summary -->
        <div class="col-xl-4">
            <x-ui-card>
                <div class="text-center">
                    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="avatar-xl rounded-circle mb-3">

                    <h4 class="mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-3">{{ $user->email }}</p>

                    @foreach ($user->roles as $role)
                        <x-ui-badge variant="info" soft class="mb-2">{{ $role->name }}</x-ui-badge>
                    @endforeach

                    <div class="mt-2">
                        <x-ui-badge :variant="$user->isActive() ? 'success' : 'danger'">
                            {{ $user->status?->label() }}
                        </x-ui-badge>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    @if ($user->business)
                        <div class="d-flex align-items-center mb-2">
                            <i class="bx bx-briefcase text-muted fs-18 me-2"></i>
                            <span>{{ $user->business->name }}</span>
                        </div>
                    @endif

                    @if ($user->shops->isNotEmpty())
                        <div class="d-flex align-items-center mb-2">
                            <i class="bx bx-store text-muted fs-18 me-2"></i>
                            <span>{{ $user->shops->pluck('name')->join(', ') }}</span>
                        </div>
                    @endif

                    <div class="d-flex align-items-center">
                        <i class="bx bx-calendar text-muted fs-18 me-2"></i>
                        <span>{{ __('Member since :date', ['date' => $user->created_at?->format('M d, Y')]) }}</span>
                    </div>
                </div>
            </x-ui-card>
        </div>

        <div class="col-xl-8">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
            @include('profile.partials.deactivate-account-form')
        </div>
    </div>
@endsection
