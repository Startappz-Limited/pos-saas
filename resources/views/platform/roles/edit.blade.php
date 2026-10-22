@extends('layouts.platform')

@section('title', __('Template: :name', ['name' => $role->displayName()]))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('platform.roles.index') }}">{{ __('Role templates') }}</a></li>
    <li class="breadcrumb-item active">{{ $role->displayName() }}</li>
@endsection

@section('content')
    @include('partials.swal-confirm-script')

    @if ($role->isOwnerRole())
        <x-ui-alert variant="warning">
            {{ __('The Owner role isn\'t copied: saving changes what every business\'s owners can do, at once.') }}
        </x-ui-alert>
    @else
        <x-ui-alert variant="info">
            {{ trans_choice('{0} No business has a copy yet.|{1} 1 business has a copy.|[2,*] :count businesses have a copy.', $copies, ['count' => $copies]) }}
            {{ __('Saving changes what new businesses get and what "Reset to default" applies; existing copies stay as they are.') }}
        </x-ui-alert>
    @endif

    <form method="POST" action="{{ route('platform.roles.update', $role) }}"
        @if ($role->isOwnerRole())
            data-swal-confirm data-swal-title="{{ __('Change the Owner role?') }}"
            data-swal-text="{{ __('Every business\'s owners get exactly these permissions, at once.') }}"
            data-swal-button="{{ __('Save for every owner') }}"
        @endif>
        @csrf
        @method('PUT')
        @if ($role->isOwnerRole())
            <input type="hidden" name="confirm" value="0">
            <noscript>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-owner">
                    <label class="form-check-label" for="confirm-owner">{{ __('Yes, change every business\'s owners') }}</label>
                </div>
            </noscript>
            @error('confirm')
                <div class="text-danger small mb-2">{{ $message }}</div>
            @enderror
        @endif

        <x-ui-card :title="__('Permissions')">
            <x-ui-permission-grid :permissions="$permissions" :selectedPermissions="old('permissions', $role->permissions->pluck('id')->toArray())" groupBy="module" />
        </x-ui-card>

        <div class="mb-4">
            <x-ui-button variant="primary" type="submit">{{ __('Save template') }}</x-ui-button>
            <x-ui-button variant="secondary" href="{{ route('platform.roles.index') }}">{{ __('Back') }}</x-ui-button>
        </div>
    </form>

    @unless ($role->isOwnerRole())
        <x-ui-card :title="__('Apply to all copies')">
            <p class="text-muted">{{ __('Replace the permissions of every business\'s copy with this template\'s, including changes a business made to its copy. Use it for platform-wide changes only.') }}</p>
            <form method="POST" action="{{ route('platform.roles.apply', $role) }}" data-swal-confirm
                data-swal-title="{{ __('Apply to all copies?') }}"
                data-swal-text="{{ trans_choice('{0} No business has a copy yet.|{1} 1 business\'s copy will get exactly this template\'s permissions.|[2,*] :count businesses\' copies will get exactly this template\'s permissions.', $copies, ['count' => $copies]) }}"
                data-swal-button="{{ __('Apply to all copies') }}">
                @csrf
                <input type="hidden" name="confirm" value="0">
                <noscript>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-apply">
                        <label class="form-check-label" for="confirm-apply">{{ __('Yes, change every business\'s copy') }}</label>
                    </div>
                </noscript>
                <x-ui-button variant="danger" type="submit">{{ __('Apply to all copies') }}</x-ui-button>
            </form>
        </x-ui-card>
    @endunless
@endsection
