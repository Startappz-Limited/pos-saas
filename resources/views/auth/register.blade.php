{{--
    Sign up: a new owner, their business and a 14-day trial.

    Kept to what is needed to start: four fields and the terms. The Free Trial is
    chosen by default; "Change" opens a window of plan cards (price, limits and
    features, all from plan data) so the plans can be compared without crowding
    the form. There is no shop count: every trial starts with one shop. A pricing
    page can preselect a tier with ?plan=tier-2. Errors show under their field,
    and the first one gets focus; a plan error opens the window.
--}}
<x-guest-layout>
    @php
        $plans = collect([$freeTrial])->filter()->merge($tiers);
        $selected = old('plan', request('plan', $freeTrial?->key));
        $selectedPlan = $plans->firstWhere('key', $selected) ?? $freeTrial;
        $features = \App\Enums\Feature::cases();
        $limits = [\App\Enums\Limit::Staff, \App\Enums\Limit::Products, \App\Enums\Limit::Registers, \App\Enums\Limit::WhatsappSessions];
        $priceLine = fn ($plan) => $plan->is_trial_plan
            ? __('Free for 14 days, 1 shop')
            : __('KES :price per shop a month after the 14-day trial', ['price' => number_format((float) $plan->price_per_shop)]);
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

        {{-- The plan: Free Trial unless changed; the cards are in the window below. --}}
        <div class="mb-3 border rounded px-3 py-2 @error('plan') border-danger @enderror">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="small">
                    {{ __('Plan') }}: <strong id="plan-summary">{{ $selectedPlan?->name ?? __('Free Trial') }}</strong>
                    <div class="text-muted" id="plan-summary-price">{{ $selectedPlan ? $priceLine($selectedPlan) : '' }}</div>
                </div>
                <button type="button" class="btn btn-link btn-sm p-0 text-nowrap" data-bs-toggle="modal" data-bs-target="#plan-modal"
                    aria-haspopup="dialog" aria-controls="plan-modal" id="plan-change">
                    {{ __('Change') }}
                </button>
            </div>
            @error('plan')<div class="text-danger small mt-1" id="plan-error">{{ $message }}</div>@enderror
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

    {{--
        The plan cards. Outside the form so the window isn't nested in it; the
        radios join the form through form="signup-form". Each card is the label
        of its radio, so arrow keys move between plans and screen readers read
        the whole card.
    --}}
    <div class="modal fade" id="plan-modal" tabindex="-1" aria-labelledby="plan-modal-title" aria-hidden="true"
        @if ($errors->has('plan')) data-open-on-load="1" @endif>
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="plan-modal-title">{{ __('Choose a plan') }}</h5>
                        <p class="text-muted small mb-0">{{ __('Every plan starts with 14 days free. No card needed. You can change plan later in Billing.') }}</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <fieldset>
                        <legend class="visually-hidden">{{ __('Plan') }}</legend>
                        <div class="row g-3">
                            @foreach ($plans as $plan)
                                <div class="col-md-6 col-xl-3">
                                    <input class="btn-check" type="radio" name="plan" form="signup-form" id="plan-{{ $plan->key }}"
                                        value="{{ $plan->key }}" data-name="{{ $plan->name }}" data-price-line="{{ $priceLine($plan) }}"
                                        @checked($selectedPlan?->key === $plan->key)>
                                    <label class="card plan-card h-100 mb-0" for="plan-{{ $plan->key }}">
                                        <span class="card-body d-flex flex-column">
                                            <span class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                                <span class="h5 mb-0">{{ $plan->name }}</span>
                                                <span class="badge bg-primary plan-selected">{{ __('Selected') }}</span>
                                            </span>
                                            <span class="mb-3">
                                                @if ($plan->is_trial_plan)
                                                    <span class="h4 d-block mb-0">{{ __('Free') }}</span>
                                                    <span class="text-muted small">{{ __('for 14 days, 1 shop') }}</span>
                                                @else
                                                    <span class="h4 d-block mb-0">KES {{ number_format((float) $plan->price_per_shop) }}</span>
                                                    <span class="text-muted small">{{ __('per shop a month, after 14 days free') }}</span>
                                                @endif
                                            </span>

                                            <span class="small fw-semibold mb-1">{{ __('Limits') }}</span>
                                            <span class="list-unstyled small mb-3 d-block">
                                                @foreach ($limits as $limit)
                                                    @php($cap = $plan->limit($limit))
                                                    <span class="d-flex justify-content-between gap-2">
                                                        <span class="text-muted">{{ ucfirst($limit->label()) }}</span>
                                                        <span>{{ $cap === null ? __('Unlimited') : number_format($cap) }}</span>
                                                    </span>
                                                @endforeach
                                            </span>

                                            <span class="small fw-semibold mb-1">{{ __('Includes') }}</span>
                                            <span class="small d-block">
                                                @foreach ($features as $feature)
                                                    @if ($plan->hasFeature($feature))
                                                        <span class="d-flex gap-1">
                                                            <iconify-icon icon="solar:check-circle-broken" class="text-success flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                                                            <span><span class="visually-hidden">{{ __('Included:') }}</span> {{ $feature->label() }}</span>
                                                        </span>
                                                    @else
                                                        <span class="d-flex gap-1 text-muted">
                                                            <iconify-icon icon="solar:close-circle-broken" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                                                            <span><span class="visually-hidden">{{ __('Not included:') }}</span> <s>{{ $feature->label() }}</s></span>
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="plan-apply">{{ __('Use this plan') }}</button>
                </div>
            </div>
        </div>
    </div>

    <p class="text-center mt-4 mb-0">
        {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('Sign in') }}</a>
    </p>

    @push('styles')
        <style>
            /* Plan cards: the chosen one is outlined in the theme's primary colour. */
            .plan-card { cursor: pointer; border: 1px solid var(--bs-border-color); transition: border-color .15s, box-shadow .15s; }
            .plan-card:hover { border-color: var(--bs-primary); }
            .plan-card .plan-selected { visibility: hidden; }
            .btn-check:checked + .plan-card { border-color: var(--bs-primary); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .25); }
            .btn-check:checked + .plan-card .plan-selected { visibility: visible; }
            .btn-check:focus-visible + .plan-card { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('signup-form');

                // The first field with an error gets focus.
                const invalid = form.querySelector('.is-invalid');
                if (invalid) {
                    invalid.focus();
                }

                // Plan: the window's choice takes effect on "Use this plan"; closing it any
                // other way puts the earlier choice back.
                const modalEl = document.getElementById('plan-modal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const radios = () => document.querySelectorAll('input[name=plan]');
                const checked = () => document.querySelector('input[name=plan]:checked');
                let before = checked();
                let applied = false;

                modalEl.addEventListener('show.bs.modal', function () {
                    before = checked();
                    applied = false;
                });
                modalEl.addEventListener('shown.bs.modal', function () {
                    (checked() || radios()[0]).focus();
                });
                modalEl.addEventListener('hidden.bs.modal', function () {
                    if (!applied && before) {
                        before.checked = true;
                    }
                    document.getElementById('plan-change').focus();
                });
                document.getElementById('plan-apply').addEventListener('click', function () {
                    const chosen = checked();
                    if (chosen) {
                        document.getElementById('plan-summary').textContent = chosen.dataset.name;
                        document.getElementById('plan-summary-price').textContent = chosen.dataset.priceLine;
                    }
                    applied = true;
                    modal.hide();
                });
                // Enter on a plan chooses it rather than submitting the whole form.
                radios().forEach(r => r.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        document.getElementById('plan-apply').click();
                    }
                }));
                if (modalEl.dataset.openOnLoad) {
                    modal.show();
                }

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
