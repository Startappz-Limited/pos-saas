@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <div class="row">
        <div class="col-xl-8 col-lg-10 mx-auto">
            <form action="{{ route('users.update', $user) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- Personal Information -->
                <x-ui-card title="Personal Information" class="mb-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <x-ui-form-input name="name" label="Full Name" :value="old('name', $user->name)" placeholder="Enter full name"
                                required />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-input name="email" type="email" label="Email Address" :value="old('email', $user->email)"
                                placeholder="user@example.com" required />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-input name="phone" type="tel" label="Phone Number" :value="old('phone', $user->phone)"
                                placeholder="+1 (555) 000-0000" />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-select name="gender" label="Gender" :value="old('gender', $user->gender)" placeholder="Select gender">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </x-ui-form-select>
                        </div>
                    </div>
                </x-ui-card>

                <!-- Account Settings -->
                <x-ui-card title="Account Settings" class="mb-3">
                    <x-ui-alert variant="info" class="mb-3">
                        Leave password fields empty to keep the current password.
                    </x-ui-alert>

                    <div class="row">
                        <div class="col-lg-6">
                            <x-ui-form-input name="password" type="password" label="New Password"
                                placeholder="Enter new password (optional)" help="Minimum 8 characters" />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-input name="password_confirmation" type="password" label="Confirm New Password"
                                placeholder="Re-enter new password" />
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-select name="role_id" label="Role" :options="$roles->pluck('name', 'id')" :value="old('role_id', $user->roles->first()?->id)"
                                placeholder="Select role" required />
                        </div>

                        <div class="col-lg-6">
                            @php($selectedShopIds = collect(old('shop_ids', $user->shops->pluck('id')->all()))->map(fn ($shopId) => (int) $shopId))
                            <div class="mb-3">
                                <label for="shop_ids" class="form-label">Allocated Shops</label>
                                <select name="shop_ids[]" id="shop_ids"
                                    class="form-select @error('shop_ids') is-invalid @enderror @error('shop_ids.*') is-invalid @enderror"
                                    multiple size="{{ min(max($shops->count(), 3), 8) }}">
                                    @foreach ($shops as $shop)
                                        <option value="{{ $shop->id }}" @selected($selectedShopIds->contains($shop->id))>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Leave empty to allow access to all shops.</div>
                                @error('shop_ids')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @error('shop_ids.*')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <x-ui-form-select name="status" label="Status" :value="old('status', $user->status?->value ?? 'active')" placeholder="Select status"
                                required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </x-ui-form-select>
                        </div>
                    </div>
                </x-ui-card>

                <!-- Profile Picture -->
                <x-ui-card title="Profile Picture" class="mb-3">
                    @if ($user->profile_photo)
                        <div class="mb-3">
                            <label class="form-label">Current Photo</label>
                            <div>
                                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="avatar-lg rounded">
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Upload New Photo</label>
                        <input type="file" class="form-control" name="avatar" accept="image/*">
                        <div class="form-text">Recommended size: 200x200px. Max file size: 2MB</div>
                    </div>
                </x-ui-card>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <x-ui-button variant="primary" type="submit" icon="solar:check-circle-broken">
                        Update User
                    </x-ui-button>
                    <x-ui-button variant="secondary" href="{{ route('users.index') }}">
                        Cancel
                    </x-ui-button>
                    @can('delete', $user)
                        <x-ui-button variant="danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                            class="float-end">
                            Delete User
                        </x-ui-button>
                    @endcan
                </div>
            </form>
        </div>
    </div>

    @can('delete', $user)
        @push('modals')
            <!-- Delete Confirmation Modal -->
            <x-ui-modal id="deleteUserModal" title="Confirm Delete" centered>
                <x-slot:body>
                    <p class="mb-0">Are you sure you want to delete this user? This action cannot be undone.</p>
                </x-slot:body>

                <x-slot:footer>
                    <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <x-ui-button variant="danger" type="submit">
                            Delete User
                        </x-ui-button>
                    </form>
                </x-slot:footer>
            </x-ui-modal>
        @endpush
    @endcan
@endsection
