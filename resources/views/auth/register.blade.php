<x-guest-layout>
    @php
        $selected = old('plan', request('plan', $freeTrial?->key));
        $limitText = function ($plan) {
            $limit = fn ($key) => $plan->limits[$key] ?? null;
            $n = fn ($v, $one, $many) => $v === null ? __('Unlimited :things', ['things' => $many]) : $v.' '.($v === 1 ? $one : $many);

            return [
                $plan->is_trial_plan ? __('1 shop') : __('Choose your number of shops'),
                $n($limit('staff'), __('staff member'), __('staff')),
                $n($limit('registers'), __('register per shop'), __('registers per shop')),
                $n($limit('whatsapp_sessions'), __('WhatsApp session'), __('WhatsApp sessions')),
                $n($limit('products'), __('product'), __('products')),
            ];
        };
    @endphp

    <div class="text-center mb-4">
        <h4 class="mb-2">{{ __('Create your account') }}</h4>
        <p class="text-muted">{{ __('14-day trial, no payment now.') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" id="signup-form">
        @csrf
        @honeypot

        <fieldset class="mb-3">
            <legend class="fs-6 fw-semibold">{{ __('Pick a plan to try') }}</legend>
            <div class="row g-2">
                @foreach (collect([$freeTrial])->filter()->merge($tiers) as $plan)
                    <div class="col-12 col-md-6">
                        <input type="radio" class="btn-check" name="plan" id="plan-{{ $plan->key }}" value="{{ $plan->key }}"
                            data-price="{{ $plan->price_per_shop }}" data-trial="{{ $plan->is_trial_plan ? 1 : 0 }}"
                            @checked($selected === $plan->key)>
                        <label class="btn btn-outline-primary w-100 text-start h-100" for="plan-{{ $plan->key }}">
                            <span class="d-block fw-semibold">{{ $plan->name }}</span>
                            <span class="d-block small">
                                @if ($plan->is_trial_plan)
                                    {{ __('Free for 14 days') }}
                                @else
                                    KES {{ number_format((float) $plan->price_per_shop) }} {{ __('per shop per month') }}
                                @endif
                            </span>
                            <ul class="small mb-1 ps-3">
                                @foreach ($limitText($plan) as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                            <span class="d-block small text-muted">{{ __('14-day trial, no payment now') }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            @error('plan')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </fieldset>

        <div class="mb-3" id="shop-count-group" hidden>
            <label for="shop_count" class="form-label">{{ __('How many shops?') }}</label>
            <input type="number" min="1" max="50" name="shop_count" id="shop_count" value="{{ old('shop_count', 1) }}"
                class="form-control @error('shop_count') is-invalid @enderror">
            @error('shop_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text" id="shop-total"></div>
        </div>

        <x-ui-form-input name="name" label="Full Name" :value="old('name')" placeholder="Enter your full name" required
            autofocus />

        <x-ui-form-input name="business_name" label="Business Name" :value="old('business_name')"
            placeholder="The name of your business" required />

        <x-ui-form-input name="email" type="email" label="Email Address" :value="old('email')"
            placeholder="Enter your email" required />

        <x-ui-form-input name="password" type="password" label="Password" placeholder="Enter your password"
            help="10 to 128 characters. A few random words make a strong password." required />
        <div class="progress mb-3" style="height: 6px" aria-hidden="true">
            <div class="progress-bar" id="password-meter" role="progressbar" style="width: 0"></div>
        </div>
        <div class="small text-muted mb-3" id="password-meter-text" aria-live="polite"></div>

        <x-ui-form-input name="password_confirmation" type="password" label="Confirm Password"
            placeholder="Re-enter your password" required />

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
            <label class="form-check-label" for="agree_terms">
                I agree to the <a href="#" class="text-primary">Terms & Conditions</a>
            </label>
        </div>

        <x-ui-button variant="primary" type="submit" class="w-100 mb-3">
            Start my 14-day trial
        </x-ui-button>
    </form>

    <div class="text-center mt-3">
        <p class="mb-0">Already have an account? <a href="{{ route('login') }}" class="text-primary fw-semibold">Sign
                In</a></p>
    </div>

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
        <script>
            (function () {
                const plans = document.querySelectorAll('input[name=plan]');
                const group = document.getElementById('shop-count-group');
                const shops = document.getElementById('shop_count');
                const total = document.getElementById('shop-total');

                function refresh() {
                    const chosen = document.querySelector('input[name=plan]:checked');
                    const paid = chosen && chosen.dataset.trial === '0';
                    group.hidden = !paid;
                    if (paid) {
                        const sum = Math.max(1, parseInt(shops.value || '1', 10)) * parseFloat(chosen.dataset.price);
                        total.textContent = @json(__('After the trial: KES')) + ' ' + sum.toLocaleString() + ' ' + @json(__('per month')) + '.';
                    }
                }
                plans.forEach(p => p.addEventListener('change', refresh));
                shops.addEventListener('input', refresh);
                refresh();

                const password = document.getElementById('password');
                const bar = document.getElementById('password-meter');
                const text = document.getElementById('password-meter-text');
                const labels = [@json(__('Very weak')), @json(__('Weak')), @json(__('Fair')), @json(__('Good')), @json(__('Strong'))];
                const colours = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];

                password.addEventListener('input', function () {
                    bar.className = 'progress-bar';
                    if (!password.value || typeof zxcvbn === 'undefined') {
                        bar.style.width = '0';
                        text.textContent = '';
                        return;
                    }
                    const score = zxcvbn(password.value, [document.getElementById('name').value, document.getElementById('email').value]).score;
                    bar.classList.add(colours[score]);
                    bar.style.width = ((score + 1) * 20) + '%';
                    text.textContent = labels[score] + (score < 3 ? ' — ' + @json(__('use a longer, less predictable password')) : '');
                });
            })();
        </script>
    @endpush
</x-guest-layout>
