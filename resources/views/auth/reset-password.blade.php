<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">Create New Password</h4>
        <p class="text-muted">Your new password must be different from previously used passwords</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui-form-input name="email" type="email" label="Email Address" :value="old('email', $request->email)"
            placeholder="Enter your email" required autofocus readonly />

        {{-- The meter shows the server's rules (StrongPassword), so no separate "minimum" hint. --}}
        <x-ui-password-input name="password" label="New Password" placeholder="Enter new password" required
            required-marker strength :personal="['email']" />

        <x-ui-password-input name="password_confirmation" label="Confirm Password" placeholder="Re-enter new password"
            required required-marker matches="password" />

        <x-ui-button variant="primary" type="submit" class="w-100 mb-3">
            Reset Password
        </x-ui-button>
    </form>

    <div class="text-center mt-3">
        <p class="mb-0">Remember your password? <a href="{{ route('login') }}" class="text-primary fw-semibold">Sign
                In</a></p>
    </div>
</x-guest-layout>
