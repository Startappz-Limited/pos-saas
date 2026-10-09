<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">Create Account</h4>
        <p class="text-muted">Get your Fitness Center account now</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-ui-form-input name="name" label="Full Name" :value="old('name')" placeholder="Enter your full name" required
            autofocus />

        <x-ui-form-input name="email" type="email" label="Email Address" :value="old('email')"
            placeholder="Enter your email" required />

        <x-ui-form-input name="password" type="password" label="Password" placeholder="Enter your password"
            help="Minimum 8 characters" required />

        <x-ui-form-input name="password_confirmation" type="password" label="Confirm Password"
            placeholder="Re-enter your password" required />

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
            <label class="form-check-label" for="agree_terms">
                I agree to the <a href="#" class="text-primary">Terms & Conditions</a>
            </label>
        </div>

        <x-ui-button variant="primary" type="submit" class="w-100 mb-3">
            Create Account
        </x-ui-button>
    </form>

    <div class="text-center mt-3">
        <p class="mb-0">Already have an account? <a href="{{ route('login') }}" class="text-primary fw-semibold">Sign
                In</a></p>
    </div>
</x-guest-layout>
