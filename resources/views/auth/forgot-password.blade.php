<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">Reset Password</h4>
        <p class="text-muted">Enter your email address and we'll send you a link to reset your password</p>
    </div>

    @if (session('status'))
        <x-ui-alert variant="success" class="mb-3">
            {{ session('status') }}
        </x-ui-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-ui-form-input name="email" type="email" label="Email Address" :value="old('email')"
            placeholder="Enter your email" required autofocus />

        <x-ui-button variant="primary" type="submit" class="w-100 mb-3">
            Send Reset Link
        </x-ui-button>
    </form>

    <div class="text-center mt-3">
        <p class="mb-0">Remember your password? <a href="{{ route('login') }}" class="text-primary fw-semibold">Sign
                In</a></p>
    </div>
</x-guest-layout>
