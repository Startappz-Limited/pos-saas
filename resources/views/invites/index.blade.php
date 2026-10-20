@extends('layouts.app')

@section('title', 'Invitations')

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <x-ui-card title="{{ __('Invite someone') }}">
                <form method="POST" action="{{ route('invites.store') }}">
                    @csrf
                    @honeypot

                    <x-ui-form-input name="email" type="email" label="{{ __('Email address') }}" :value="old('email')"
                        placeholder="name@example.com" required />

                    <x-ui-form-select name="role_id" label="{{ __('Role') }}" :options="$roles->pluck('name', 'id')"
                        :value="old('role_id')" placeholder="{{ __('Select role') }}" required />

                    @if ($shops->isNotEmpty())
                        <div class="mb-3">
                            <label class="form-label">{{ __('Shops') }}</label>
                            @foreach ($shops as $shop)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="shop_ids[]" value="{{ $shop->id }}"
                                        id="invite_shop_{{ $shop->id }}" @checked(in_array($shop->id, old('shop_ids', [])))>
                                    <label class="form-check-label" for="invite_shop_{{ $shop->id }}">{{ $shop->name }}</label>
                                </div>
                            @endforeach
                            <div class="form-text">{{ __('Owners see every shop; everyone else only the shops given here.') }}</div>
                            @error('shop_ids.*')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Send invitation') }}</x-ui-button>
                </form>
            </x-ui-card>
        </div>

        <div class="col-lg-7">
            <x-ui-card title="{{ __('Pending invitations') }}">
                @if ($invites->isEmpty())
                    <p class="text-muted mb-0">{{ __('No pending invitations.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Role') }}</th>
                                    <th>{{ __('Expires') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invites as $invite)
                                    <tr>
                                        <td>
                                            {{ $invite->email }}
                                            @if ($invite->inviter)
                                                <div class="small text-muted">{{ __('by :name', ['name' => $invite->inviter->name]) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $invite->role?->name }}</td>
                                        <td>{{ $invite->expires_at->toFormattedDayDateString() }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" action="{{ route('invites.resend', $invite) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light">{{ __('Resend') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('invites.destroy', $invite) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Withdraw') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui-card>
        </div>
    </div>
@endsection
