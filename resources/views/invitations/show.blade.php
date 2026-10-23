<x-guest-layout>
    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('Join :business', ['business' => $invite->business->name]) }}</h4>
        <p class="text-muted">
            {{ __('You are invited as :role. This invitation is for :email.', ['role' => $invite->role?->displayName(), 'email' => $invite->email]) }}
        </p>
    </div>


    @if ($user && strtolower($user->email) === $invite->email)
        <form method="POST" action="{{ route('invitations.accept', $token) }}">
            @csrf
            <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Accept the invitation') }}</x-ui-button>
        </form>
    @elseif ($user)
        <div class="alert alert-warning" role="alert">
            {{ __('You are signed in as :email. Sign out, then open the link again and sign in as :invited.', ['email' => $user->email, 'invited' => $invite->email]) }}
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Sign out') }}</x-ui-button>
        </form>
    @elseif ($hasAccount)
        <p class="text-center">{{ __('You already have an account. Sign in to accept.') }}</p>
        <x-ui-button variant="primary" href="{{ route('invitations.sign-in', $token) }}" class="w-100">{{ __('Sign in') }}</x-ui-button>
    @else
        {{-- First sign-in (fix/invite-first-sign-in): a name and a password, with a way to see what was typed. --}}
        <form method="POST" action="{{ route('invitations.register', $token) }}" id="invite-form" novalidate>
            @csrf
            @honeypot
            <div class="mb-3">
                <label for="name" class="form-label">{{ __('Your name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $invite->name) }}" required maxlength="255"
                    autocomplete="name" @if (! $invite->name) autofocus @endif
                    class="form-control @error('name') is-invalid @enderror"
                    @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')<div class="invalid-feedback" id="name-error">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('Choose a password') }}</label>
                <div class="input-group has-validation">
                    <input type="password" id="password" name="password" required maxlength="128" autocomplete="new-password"
                        @if ($invite->name) autofocus @endif
                        class="form-control @error('password') is-invalid @enderror"
                        aria-describedby="password-help @error('password') password-error @enderror"
                        @error('password') aria-invalid="true" @enderror>
                    <button type="button" class="btn btn-outline-secondary" id="password-toggle" aria-controls="password"
                        aria-pressed="false" aria-label="{{ __('Show password') }}">
                        <iconify-icon icon="solar:eye-broken" class="align-middle" aria-hidden="true"></iconify-icon>
                    </button>
                    @error('password')<div class="invalid-feedback" id="password-error">{{ $message }}</div>@enderror
                </div>
                <div class="progress mt-2" style="height: 4px" aria-hidden="true">
                    <div class="progress-bar" id="password-meter" style="width: 0"></div>
                </div>
                <div class="form-text" id="password-help" aria-live="polite">{{ __('At least 10 characters. A few random words work well; common or leaked passwords are refused.') }}</div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="invite-submit">
                {{ __('Join :business', ['business' => $invite->business->name]) }}
            </button>
        </form>

        @push('scripts')
            <script>
                (function () {
                    const form = document.getElementById('invite-form');
                    const password = document.getElementById('password');
                    const toggle = document.getElementById('password-toggle');

                    const invalid = form.querySelector('.is-invalid');
                    if (invalid) {
                        invalid.focus();
                    }

                    // Show or hide the password, in place of a second "confirm" field.
                    toggle.addEventListener('click', function () {
                        const show = password.type === 'password';
                        password.type = show ? 'text' : 'password';
                        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                        toggle.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
                    });

                    // Strength guide: zxcvbn loads once the field is used; the server decides.
                    const bar = document.getElementById('password-meter');
                    const help = document.getElementById('password-help');
                    const defaultHelp = help.textContent;
                    const labels = [@json(__('Very weak')), @json(__('Weak')), @json(__('Fair')), @json(__('Good')), @json(__('Strong'))];
                    const colours = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];

                    function score() {
                        bar.className = 'progress-bar';
                        if (!password.value || typeof zxcvbn === 'undefined') {
                            bar.style.width = '0';
                            help.textContent = defaultHelp;
                            return;
                        }
                        const result = zxcvbn(password.value, [document.getElementById('name').value, @json($invite->email)]).score;
                        bar.classList.add(colours[result]);
                        bar.style.width = ((result + 1) * 20) + '%';
                        help.textContent = labels[result] + (result < 3 ? ' — ' + @json(__('make it longer or less predictable')) : '');
                    }

                    password.addEventListener('focus', function () {
                        if (typeof zxcvbn !== 'undefined') {
                            return;
                        }
                        const script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js';
                        script.onload = score;
                        document.head.appendChild(script);
                    }, { once: true });
                    password.addEventListener('input', score);

                    form.addEventListener('submit', function () {
                        document.getElementById('invite-submit').disabled = true;
                    });
                })();
            </script>
        @endpush
    @endif
</x-guest-layout>
