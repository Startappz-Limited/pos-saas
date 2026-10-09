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

        <x-ui-form-input name="password" type="password" label="New Password" placeholder="Enter new password"
            help="Minimum 8 characters" required />

        <x-ui-form-input name="password_confirmation" type="password" label="Confirm Password"
            placeholder="Re-enter new password" required />

        <x-ui-button variant="primary" type="submit" class="w-100 mb-3">
            Reset Password
        </x-ui-button>
    </form>

    <div class="text-center mt-3">
        <p class="mb-0">Remember your password? <a href="{{ route('login') }}" class="text-primary fw-semibold">Sign
                In</a></p>
    </div>
</x-guest-layout>
