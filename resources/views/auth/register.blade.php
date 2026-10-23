{{--
    Sign up: a new owner, their business and a 14-day trial.

    Kept to what is needed to start: four fields and the terms. The Free Trial is
    chosen by default; the other plans are folded away behind "Change", so most
    people never see them. A pricing page can still preselect a tier with
    ?plan=tier-2, which opens the panel. Errors show under their field, and the
    first one gets focus.
--}}
<x-guest-layout>
    @php
        $plans = collect([$freeTrial])->filter()->merge($tiers);
        $selected = old('plan', request('plan', $freeTrial?->key));
        $selectedPlan = $plans->firstWhere('key', $selected) ?? $freeTrial;
        $planOpen = ($selectedPlan && ! $selectedPlan->is_trial_plan) || $errors->hasAny(['plan', 'shop_count']);
        $termsUrl = config('legal.terms_url');
        $privacyUrl = config('legal.privacy_url');
        $describedBy = fn (string $field, ?string $help = null) => trim(($help ?? '').' '.($errors->has($field) ? $field.'-error' : ''));
    @endphp

    <div class="text-center mb-4">
        <h4 class="mb-1">{{ __('Start your free 14-day trial') }}</h4>
        <p class="text-muted mb-0">{{ __('No card needed. Set up in a minute.') }}</p>
    </div>

    @if ($errors->any())
        <div class="visually-hidden" role="alert">
            {{ trans_choice('{1} There is 1 problem with the form.|[2,*] There are :count problems with the form.', $errors->count(), ['count' => $errors->count()]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" id="signup-form" novalidate>
        @csrf
        @honeypot

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('Your name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                maxlength="255" class="form-control @error('name') is-invalid @enderror"
                @if ($describedBy('name')) aria-describedby="{{ $describedBy('name') }}" @endif
                @error('name') aria-invalid="true" @enderror>
            @error('name')<div class="invalid-feedback" id="name-error">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="business_name" class="form-label">{{ __('Business name') }}</label>
            <input type="text" id="business_name" name="business_name" value="{{ old('business_name') }}" required
                autocomplete="organization" maxlength="255" class="form-control @error('business_name') is-invalid @enderror"
                @if ($describedBy('business_name')) aria-describedby="{{ $describedBy('business_name') }}" @endif
                @error('business_name') aria-invalid="true" @enderror>
            @error('business_name')<div class="invalid-feedback" id="business_name-error">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                inputmode="email" maxlength="255" class="form-control @error('email') is-invalid @enderror"
                @if ($describedBy('email')) aria-describedby="{{ $describedBy('email') }}" @endif
                @error('email') aria-invalid="true" @enderror>
            @error('email')
                <div class="invalid-feedback" id="email-error">
                    {{ $message }}
                    @if (str_contains(strtolower($message), 'already'))
                        <a href="{{ route('login') }}">{{ __('Sign in instead') }}</a>
                    @endif
                </div>
            @enderror
            <div class="form-text">{{ __('We\'ll send a link to confirm it.') }}</div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <div class="input-group has-validation">
                <input type="password" id="password" name="password" required autocomplete="new-password" maxlength="128"
                    class="form-control @error('password') is-invalid @enderror"
                    aria-describedby="{{ $describedBy('password', 'password-help') }}"
                    @error('password') aria-invalid="true" @enderror>
                <button type="button" class="btn btn-outline-secondary" id="password-toggle"
                    aria-controls="password" aria-pressed="false" aria-label="{{ __('Show password') }}">
                    <iconify-icon icon="solar:eye-broken" class="align-middle" aria-hidden="true"></iconify-icon>
                </button>
                @error('password')<div class="invalid-feedback" id="password-error">{{ $message }}</div>@enderror
            </div>
            <div class="progress mt-2" style="height: 4px" aria-hidden="true">
                <div class="progress-bar" id="password-meter" style="width: 0"></div>
            </div>
            <div class="form-text" id="password-help" aria-live="polite">{{ __('At least 10 characters. A few random words work well.') }}</div>
        </div>

        {{-- The plan: Free Trial unless changed; the choices stay folded away. --}}
        <div class="mb-3 border rounded px-3 py-2">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="small">
                    {{ __('Plan') }}: <strong id="plan-summary">{{ $selectedPlan?->name ?? __('Free Trial') }}</strong>
                </span>
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#plan-panel"
                    aria-expanded="{{ $planOpen ? 'true' : 'false' }}" aria-controls="plan-panel">
                    {{ __('Change') }}
                </button>
            </div>

            <div class="collapse {{ $planOpen ? 'show' : '' }}" id="plan-panel">
                <fieldset class="pt-2">
                    <legend class="visually-hidden">{{ __('Plan') }}</legend>
                    @foreach ($plans as $plan)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="plan" id="plan-{{ $plan->key }}" value="{{ $plan->key }}"
                                data-name="{{ $plan->name }}" data-price="{{ $plan->price_per_shop }}" data-trial="{{ $plan->is_trial_plan ? 1 : 0 }}"
                                @checked($selectedPlan?->key === $plan->key)>
                            <label class="form-check-label small" for="plan-{{ $plan->key }}">
                                {{ $plan->name }} ·
                                @if ($plan->is_trial_plan)
                                    {{ __('free, 1 shop') }}
                                @else
                                    KES {{ number_format((float) $plan->price_per_shop) }} {{ __('per shop a month after the trial') }}
                                @endif
                            </label>
                        </div>
                    @endforeach
                    @error('plan')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

                    <div class="mt-2" id="shop-count-group" @if ($selectedPlan?->is_trial_plan ?? true) hidden @endif>
                        <label for="shop_count" class="form-label small mb-1">{{ __('How many shops?') }}</label>
                        <input type="number" min="1" max="50" name="shop_count" id="shop_count" value="{{ old('shop_count', 1) }}"
                            inputmode="numeric" class="form-control form-control-sm w-auto @error('shop_count') is-invalid @enderror"
                            aria-describedby="shop-total">
                        @error('shop_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="shop-total"></div>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input @error('agree_terms') is-invalid @enderror" type="checkbox" id="agree_terms" name="agree_terms"
                value="1" required @checked(old('agree_terms'))
                @error('agree_terms') aria-invalid="true" aria-describedby="agree_terms-error" @enderror>
            <label class="form-check-label" for="agree_terms">
                {{ __('I agree to the') }}
                @if ($termsUrl)
                    <a href="{{ $termsUrl }}" target="_blank" rel="noopener">{{ __('Terms') }}</a>
                @else
                    {{ __('Terms') }}
                @endif
                {{ __('and') }}
                @if ($privacyUrl)
                    <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>
                @else
                    {{ __('Privacy Policy') }}
                @endif
            </label>
            @error('agree_terms')<div class="invalid-feedback" id="agree_terms-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100" id="signup-submit">
            <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>
            {{ __('Start free trial') }}
        </button>
    </form>

    <p class="text-center mt-4 mb-0">
        {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('Sign in') }}</a>
    </p>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('signup-form');

                // The first field with an error gets focus.
                const invalid = form.querySelector('.is-invalid');
                if (invalid) {
                    invalid.focus();
                }

                // Plan: the summary line follows the choice; shops only for a paid tier.
                const group = document.getElementById('shop-count-group');
                const shops = document.getElementById('shop_count');
                const total = document.getElementById('shop-total');
                const summary = document.getElementById('plan-summary');

                function refreshPlan() {
                    const chosen = form.querySelector('input[name=plan]:checked');
                    if (!chosen) {
                        return;
                    }
                    summary.textContent = chosen.dataset.name;
                    const paid = chosen.dataset.trial === '0';
                    group.hidden = !paid;
                    if (paid) {
                        const sum = Math.max(1, parseInt(shops.value || '1', 10)) * parseFloat(chosen.dataset.price);
                        total.textContent = @json(__('After the trial: KES')) + ' ' + sum.toLocaleString() + ' ' + @json(__('a month.'));
                    }
                }
                form.querySelectorAll('input[name=plan]').forEach(p => p.addEventListener('change', refreshPlan));
                shops.addEventListener('input', refreshPlan);
                refreshPlan();

                // Show or hide the password, in place of a second "confirm" field.
                const password = document.getElementById('password');
                const toggle = document.getElementById('password-toggle');
                toggle.addEventListener('click', function () {
                    const show = password.type === 'password';
                    password.type = show ? 'text' : 'password';
                    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                    toggle.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
                });

                // Strength meter: zxcvbn (about 800 KB) loads only once the password field is used.
                // The server's check decides; this only guides.
                const bar = document.getElementById('password-meter');
                const help = document.getElementById('password-help');
                const defaultHelp = help.textContent;
                const labels = [@json(__('Very weak')), @json(__('Weak')), @json(__('Fair')), @json(__('Good')), @json(__('Strong'))];
                const colours = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
                let loading = false;

                function loadMeter() {
                    if (loading || typeof zxcvbn !== 'undefined') {
                        return;
                    }
                    loading = true;
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js';
                    script.onload = score;
                    document.head.appendChild(script);
                }

                function score() {
                    bar.className = 'progress-bar';
                    if (!password.value || typeof zxcvbn === 'undefined') {
                        bar.style.width = '0';
                        help.textContent = defaultHelp;
                        return;
                    }
                    const result = zxcvbn(password.value, [document.getElementById('name').value, document.getElementById('email').value]).score;
                    bar.classList.add(colours[result]);
                    bar.style.width = ((result + 1) * 20) + '%';
                    help.textContent = labels[result] + (result < 3 ? ' — ' + @json(__('make it longer or less predictable')) : '');
                }

                password.addEventListener('focus', loadMeter, { once: true });
                password.addEventListener('input', score);

                // One submission: the button waits once clicked.
                form.addEventListener('submit', function () {
                    const button = document.getElementById('signup-submit');
                    button.disabled = true;
                    button.querySelector('.spinner-border').classList.remove('d-none');
                });
            })();
        </script>
    @endpush
</x-guest-layout>
