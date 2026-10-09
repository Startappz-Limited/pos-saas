<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">Welcome Back!</h4>
        <p class="text-muted">Sign in to continue to Fitness Center</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-ui-form-input name="email" type="email" label="Email Address" :value="old('email')"
            placeholder="Enter your email" required autofocus />

        <x-ui-form-input name="password" type="password" label="Password" placeholder="Enter your password" required />

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
            <label class="form-check-label" for="remember_me">
                Remember me
            </label>
        </div>

        <x-ui-button variant="primary" type="submit" class="w-100 mb-3">
            Sign In
        </x-ui-button>

        @if (Route::has('password.request'))
            <div class="text-center">
                <a href="{{ route('password.request') }}" class="text-muted">
                    <i class="bx bx-lock-alt me-1"></i> Forgot your password?
                </a>
            </div>
        @endif
    </form>

    @if (Route::has('register'))
        <div class="text-center mt-3">
            <p class="mb-0">Don't have an account? <a href="{{ route('register') }}"
                    class="text-primary fw-semibold">Sign Up</a></p>
        </div>
    @endif
</x-guest-layout>
