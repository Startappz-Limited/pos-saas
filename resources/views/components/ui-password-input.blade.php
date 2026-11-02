{{--
    UI Component: Password Input

    A password field with a show/hide button, and optionally a strength meter
    (for a new password) or a "passwords match" line (for its confirmation).

    Usage:
    <x-ui-password-input name="password" label="Password" strength :personal="['name', 'email']" required />
    <x-ui-password-input name="password_confirmation" label="Confirm Password" matches="password" required />

    Props:
    - name: Input name (also the id unless `id` is given)
    - label: Label text
    - required: Mark the input required (boolean)
    - requiredMarker: Show a red * after the label (boolean)
    - strength: Show the strength meter and the server's requirements (boolean)
    - personal: ids of fields whose words the password must not contain (the server's rule)
    - matches: id of the password field this one confirms
    - help: Help text below the input (optional)
    - autofocus, placeholder: passed to the input

    The meter mirrors App\Rules\StrongPassword: 10 to 128 characters, none of the
    person's own words, no repeated or sequential pattern, not a common password.
    The server's check decides. It also refuses passwords from a 10,000-word list
    and from known leaks (Have I Been Pwned); the browser checks only the most
    common words, so the meter tops out at "Strong" only for what it can check,
    and the last line of the checklist says the rest is checked on submit.
    Everything runs in the browser: nothing is sent until the form is.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'required' => false,
    'requiredMarker' => false,
    'strength' => false,
    'personal' => [],
    'matches' => null,
    'help' => null,
])

@php
    $id ??= $name;
    $described = collect([
        $help ? $id.'-help' : null,
        $strength ? $id.'-strength' : null,
        $matches ? $id.'-match' : null,
        $errors->has($name) ? $id.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="mb-3" data-password-field>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if ($requiredMarker)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <div class="input-group has-validation">
        <input type="password" id="{{ $id }}" name="{{ $name }}" maxlength="{{ \App\Rules\StrongPassword::MAX }}"
            autocomplete="new-password" @if ($required) required @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
            @if ($described) aria-describedby="{{ $described }}" @endif
            @error($name) aria-invalid="true" @enderror
            data-password-input
            @if ($strength) data-strength data-min="{{ \App\Rules\StrongPassword::MIN }}" data-max="{{ \App\Rules\StrongPassword::MAX }}" @endif
            @if ($personal) data-personal="{{ implode(',', $personal) }}" @endif
            @if ($matches) data-matches="{{ $matches }}" @endif>
        <button type="button" class="btn btn-outline-secondary" data-password-toggle aria-controls="{{ $id }}"
            aria-pressed="false" aria-label="{{ __('Show password') }}">
            <iconify-icon icon="solar:eye-broken" class="align-middle" aria-hidden="true"></iconify-icon>
        </button>
        @error($name)
            <div class="invalid-feedback" id="{{ $id }}-error">{{ $message }}</div>
        @enderror
    </div>

    @if ($strength)
        <div class="mt-2 d-none" id="{{ $id }}-strength" data-password-strength>
            <div class="d-flex gap-1" aria-hidden="true">
                @for ($i = 0; $i < 4; $i++)
                    <div class="password-segment flex-fill rounded-pill bg-secondary-subtle"></div>
                @endfor
            </div>
            <div class="small mt-1" aria-live="polite">
                {{ __('Strength:') }} <span class="fw-semibold" data-strength-label></span>
            </div>
            <ul class="list-unstyled small mb-0 mt-1">
                <li class="d-flex gap-1" data-rule="length">
                    <iconify-icon icon="solar:close-circle-broken" class="flex-shrink-0 mt-1 text-danger" aria-hidden="true"></iconify-icon>
                    <span class="visually-hidden" data-rule-state>{{ __('Not met:') }}</span>
                    <span>{{ __(':min to :max characters', ['min' => \App\Rules\StrongPassword::MIN, 'max' => \App\Rules\StrongPassword::MAX]) }}</span>
                </li>
                @if ($personal)
                    <li class="d-flex gap-1" data-rule="personal">
                        <iconify-icon icon="solar:close-circle-broken" class="flex-shrink-0 mt-1 text-danger" aria-hidden="true"></iconify-icon>
                        <span class="visually-hidden" data-rule-state>{{ __('Not met:') }}</span>
                        <span>{{ in_array('name', $personal, true) ? __('Not your name, email or business name') : __('Not your email') }}</span>
                    </li>
                @endif
                <li class="d-flex gap-1" data-rule="pattern">
                    <iconify-icon icon="solar:close-circle-broken" class="flex-shrink-0 mt-1 text-danger" aria-hidden="true"></iconify-icon>
                    <span class="visually-hidden" data-rule-state>{{ __('Not met:') }}</span>
                    <span>{{ __('No pattern like aaaaaa, 123456 or qwerty') }}</span>
                </li>
                <li class="d-flex gap-1 text-muted" data-rule="common">
                    <iconify-icon icon="solar:info-circle-broken" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                    <span class="visually-hidden" data-rule-state></span>
                    <span>{{ __('Not a common or leaked password (checked on submit)') }}</span>
                </li>
            </ul>
        </div>
    @endif

    @if ($matches)
        <div class="small mt-1 d-none" id="{{ $id }}-match" aria-live="polite" data-password-match></div>
    @endif

    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
</div>

@once
    @push('styles')
        <style>
            .password-segment { height: 4px; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                const text = {
                    show: @json(__('Show password')),
                    hide: @json(__('Hide password')),
                    met: @json(__('Met:')),
                    notMet: @json(__('Not met:')),
                    match: @json(__('Passwords match')),
                    noMatch: @json(__('Passwords don\'t match')),
                    labels: [@json(__('Weak')), @json(__('Fair')), @json(__('Good')), @json(__('Strong'))],
                };
                const colours = ['bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
                const textColours = ['text-danger', 'text-warning', 'text-info', 'text-success'];

                // From database/data/common-passwords.txt, which the server checks in full (with letter
                // swaps undone and digits trimmed, as below): the 120 most common base words, and every
                // entry long enough to pass the length rule as typed (about 64).
                @php($commonList = collect(file(database_path('data/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))->map(fn ($w) => trim($w)))
                const common = new Set(@json($commonList->filter(fn ($w) => preg_match('/^[a-z]{4,}$/', $w) === 1)->take(120)->merge($commonList->filter(fn ($w) => mb_strlen($w) >= \App\Rules\StrongPassword::MIN))->unique()->values()));
                const runs = ['0123456789', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'];
                const swaps = { '@': 'a', '4': 'a', '0': 'o', '3': 'e', '$': 's', '5': 's', '7': 't', '!': 'i', '|': 'l', '+': 't' };

                function swap(value, one) {
                    return [...value].map(c => c === '1' ? one : (swaps[c] ?? c)).join('');
                }

                // The same variants as StrongPassword::variants().
                function variants(lower) {
                    const trim = v => v.replace(/^[\d\W_]+|[\d\W_]+$/g, '');
                    const forms = [lower, swap(lower, 'i'), swap(lower, 'l')];
                    forms.push(...forms.map(trim));
                    const core = lower.replace(/[\d\W_]+$/, '');
                    forms.push(swap(core, 'i'), swap(core, 'l'));
                    return forms;
                }

                function isTrivial(lower) {
                    if (/^(.)\1+$/u.test(lower)) {
                        return true;
                    }
                    const alnum = lower.replace(/[^a-z0-9]/g, '');
                    const reverse = s => [...s].reverse().join('');
                    return alnum.length >= 6 && runs.some(run => run.includes(alnum) || reverse(run).includes(alnum));
                }

                // Words of 3 or more characters from the person's own details (StrongPassword::personalWords()).
                function personalWords(ids) {
                    const words = [];
                    ids.forEach(function (id) {
                        const field = document.getElementById(id);
                        let source = field ? field.value.toLowerCase() : '';
                        if (id === 'email') {
                            source = source.split('@')[0];
                        }
                        words.push(source.replace(/[^a-z0-9]/g, ''), ...source.split(/[^a-z0-9]+/));
                    });
                    return words.filter(w => w.length >= 3);
                }

                function evaluate(input) {
                    const value = input.value;
                    const lower = value.toLowerCase();
                    const length = [...value].length;
                    const ids = (input.dataset.personal || '').split(',').filter(Boolean);
                    const rules = {
                        length: length >= Number(input.dataset.min) && length <= Number(input.dataset.max),
                        personal: !personalWords(ids).some(w => lower.includes(w)),
                        pattern: !isTrivial(lower),
                        common: !variants(lower).some(v => v !== '' && common.has(v)),
                    };

                    // Any rule the server would fail keeps it at Weak; above that, length and variety.
                    let score = 0;
                    if (Object.values(rules).every(Boolean)) {
                        const variety = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter(r => r.test(value)).length;
                        score = 1;
                        if ((length >= 14 && variety >= 2) || length >= 18) {
                            score = 2;
                        }
                        if ((length >= 16 && variety >= 3) || length >= 22) {
                            score = 3;
                        }
                    }

                    return { rules, score };
                }

                function setRule(item, met) {
                    if (!item) {
                        return;
                    }
                    const icon = item.querySelector('iconify-icon');
                    icon.setAttribute('icon', met ? 'solar:check-circle-broken' : 'solar:close-circle-broken');
                    icon.classList.toggle('text-success', met);
                    icon.classList.toggle('text-danger', !met);
                    item.querySelector('[data-rule-state]').textContent = met ? text.met : text.notMet;
                }

                function renderStrength(input) {
                    const box = document.getElementById(input.id + '-strength');
                    if (!box) {
                        return;
                    }
                    box.classList.toggle('d-none', input.value === '');
                    if (input.value === '') {
                        return;
                    }
                    const { rules, score } = evaluate(input);
                    box.querySelectorAll('.password-segment').forEach(function (segment, i) {
                        segment.classList.remove('bg-secondary-subtle', ...colours);
                        segment.classList.add(i <= score ? colours[score] : 'bg-secondary-subtle');
                    });
                    const label = box.querySelector('[data-strength-label]');
                    label.textContent = text.labels[score];
                    label.classList.remove(...textColours);
                    label.classList.add(textColours[score]);
                    ['length', 'personal', 'pattern'].forEach(rule => setRule(box.querySelector('[data-rule="' + rule + '"]'), rules[rule]));

                    // The common-password line only turns red for a word the browser knows; the rest is the server's.
                    const commonItem = box.querySelector('[data-rule="common"]');
                    const icon = commonItem.querySelector('iconify-icon');
                    icon.setAttribute('icon', rules.common ? 'solar:info-circle-broken' : 'solar:close-circle-broken');
                    icon.classList.toggle('text-danger', !rules.common);
                    commonItem.classList.toggle('text-muted', rules.common);
                    commonItem.classList.toggle('text-danger', !rules.common);
                    commonItem.querySelector('[data-rule-state]').textContent = rules.common ? '' : text.notMet;
                }

                function renderMatch(confirm) {
                    const line = document.getElementById(confirm.id + '-match');
                    const original = document.getElementById(confirm.dataset.matches);
                    if (!line || !original || confirm.value === '') {
                        line?.classList.add('d-none');
                        return;
                    }
                    const same = confirm.value === original.value;
                    line.classList.remove('d-none', 'text-success', 'text-danger');
                    line.classList.add(same ? 'text-success' : 'text-danger');
                    line.textContent = same ? text.match : text.noMatch;
                }

                document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const input = document.getElementById(button.getAttribute('aria-controls'));
                        const show = input.type === 'password';
                        input.type = show ? 'text' : 'password';
                        button.setAttribute('aria-pressed', show ? 'true' : 'false');
                        button.setAttribute('aria-label', show ? text.hide : text.show);
                        button.querySelector('iconify-icon').setAttribute('icon', show ? 'solar:eye-closed-broken' : 'solar:eye-broken');
                    });
                });

                document.querySelectorAll('[data-password-input]').forEach(function (input) {
                    if (input.hasAttribute('data-strength')) {
                        input.addEventListener('input', () => renderStrength(input));
                        // Changing a name or email changes which words are refused.
                        (input.dataset.personal || '').split(',').filter(Boolean).forEach(function (id) {
                            document.getElementById(id)?.addEventListener('input', () => renderStrength(input));
                        });
                        renderStrength(input);
                    }
                    if (input.dataset.matches) {
                        input.addEventListener('input', () => renderMatch(input));
                        document.getElementById(input.dataset.matches)?.addEventListener('input', () => renderMatch(input));
                        renderMatch(input);
                    }
                });
            })();
        </script>
    @endpush
@endonce
