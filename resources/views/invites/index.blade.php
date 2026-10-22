@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <x-ui-card title="{{ __('Add a user') }}">
                <p class="small text-muted">
                    {{ __('We email them a link. They choose their own password, so you never set or see it. The link works for :hours hours.', ['hours' => \App\Models\BusinessInvite::EXPIRES_AFTER_HOURS]) }}
                    @if ($seatLimit !== null)
                        <br>{{ __(':used of :limit staff places are used (pending invitations count).', ['used' => $seatsUsed, 'limit' => $seatLimit]) }}
                    @endif
                </p>
                <form method="POST" action="{{ route('invites.store') }}">
                    @csrf
                    @honeypot

                    <x-ui-form-input name="email" type="email" label="{{ __('Email address') }}" :value="old('email')"
                        placeholder="name@yourshop.co.ke" required />

                    <x-ui-form-select name="role_id" label="{{ __('Role') }}" :options="$roles->mapWithKeys(fn ($role) => [$role->id => $role->displayName()])"
                        :value="old('role_id')" placeholder="{{ __('Select role') }}" required />

                    <x-ui-form-input name="name" label="{{ __('Name (optional)') }}" :value="old('name')"
                        help="{{ __('If you leave it blank they type it in when they accept.') }}" />

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
                                    <th>{{ __('Person') }}</th>
                                    <th>{{ __('Role') }}</th>
                                    <th>{{ __('Expires') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invites as $invite)
                                    <tr>
                                        <td>
                                            {{ $invite->name ?: $invite->email }}
                                            <span class="badge bg-warning-subtle text-warning">{{ __('Pending') }}</span>
                                            @if ($invite->name)
                                                <div class="small text-muted">{{ $invite->email }}</div>
                                            @endif
                                            @if ($invite->inviter)
                                                <div class="small text-muted">{{ __('by :name', ['name' => $invite->inviter->name]) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('invites.role', $invite) }}" class="d-flex gap-1">
                                                @csrf
                                                @honeypot
                                                @method('PATCH')
                                                <select name="role_id" class="form-select form-select-sm" aria-label="{{ __('Role for :email', ['email' => $invite->email]) }}">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" @selected($role->id === $invite->role_id)>{{ $role->displayName() }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-light">{{ __('Change') }}</button>
                                            </form>
                                        </td>
                                        <td>{{ $invite->expires_at->toDayDateTimeString() }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" action="{{ route('invites.resend', $invite) }}">
                                                    @csrf
                                                    @honeypot
                                                    <button type="submit" class="btn btn-sm btn-light">{{ __('Resend') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('invites.destroy', $invite) }}">
                                                    @csrf
                                                    @honeypot
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button>
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
