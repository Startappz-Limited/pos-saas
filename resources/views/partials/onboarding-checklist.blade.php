{{--
    The new-business checklist (stage 3). Shown on the dashboard until every
    step is done; each step ticks itself off from the business's data.
    Expects $steps (from the view composer).
--}}
@if (collect($steps)->contains(fn ($step) => ! $step['done']))
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('Get started') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($steps as $step)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <iconify-icon icon="{{ $step['done'] ? 'solar:check-circle-bold' : 'solar:record-circle-linear' }}"
                            class="align-middle me-2 {{ $step['done'] ? 'text-success' : 'text-muted' }}"></iconify-icon>
                        <span @class(['text-decoration-line-through text-muted' => $step['done']])>{{ $step['label'] }}</span>
                        <span class="small text-muted ms-2">{{ $step['hint'] }}</span>
                    </div>
                    @unless ($step['done'])
                        <a href="{{ $step['url'] }}" class="btn btn-sm btn-soft-primary">{{ __('Do this') }}</a>
                    @endunless
                </li>
            @endforeach
        </ul>
    </div>
@endif
