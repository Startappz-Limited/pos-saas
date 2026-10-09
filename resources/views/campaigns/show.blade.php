@extends('layouts.app')

@section('title', $campaign->name)

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">{{ $campaign->name }} <small class="text-muted">{{ $campaign->code }}</small></h3>
            <span class="badge bg-{{ $campaign->status?->color() }}">{{ $campaign->status?->label() }}</span>
            <span class="badge bg-info-subtle text-info">{{ $campaign->channel?->label() }}</span>
        </div>
        <div class="d-flex gap-2">
            @can('publish', $campaign)
                @if ($campaign->status?->value !== 'active')
                    <form method="POST" action="{{ route('campaigns.start', $campaign) }}">@csrf
                        <button class="btn btn-success btn-sm">Start</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('campaigns.pause', $campaign) }}">@csrf
                        <button class="btn btn-warning btn-sm">Pause</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('campaigns.complete', $campaign) }}">@csrf
                    <button class="btn btn-secondary btn-sm">Complete</button>
                </form>
            @endcan
            @can('update', $campaign)
                <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-light btn-sm">Edit</a>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Posts</h5>
                </div>
                <div class="card-body">
                    @forelse ($campaign->posts as $post)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge bg-{{ $post->platform?->color() ?? 'secondary' }}">
                                        <iconify-icon
                                            icon="{{ $post->platform?->icon() ?? 'solar:share-bold-duotone' }}"></iconify-icon>
                                        {{ $post->platform?->label() }}
                                    </span>
                                    <span
                                        class="badge bg-{{ $post->status?->color() ?? 'secondary' }}">{{ $post->status?->label() }}</span>
                                    @if ($post->scheduled_at)
                                        <small class="text-muted ms-2">scheduled:
                                            {{ $post->scheduled_at->format('Y-m-d H:i') }}</small>
                                    @endif
                                </div>
                                <div>
                                    @if ($post->external_post_url)
                                        <a href="{{ $post->external_post_url }}" target="_blank"
                                            class="btn btn-sm btn-light">View</a>
                                    @endif
                                    @can('publish', $campaign)
                                        @if (in_array($post->status?->value, ['draft', 'scheduled', 'failed']))
                                            <form method="POST"
                                                action="{{ route('campaigns.posts.publish', [$campaign, $post->uuid]) }}"
                                                class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-primary">Publish now</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                            <p class="mb-1 mt-2">{{ $post->caption }}</p>
                            @if (!empty($post->hashtags))
                                <small
                                    class="text-info">{{ collect($post->hashtags)->map(fn($t) => '#' . ltrim($t, '#'))->implode(' ') }}</small>
                            @endif
                            @if ($post->last_error)
                                <div class="alert alert-danger mt-2 mb-0">{{ $post->last_error }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No posts yet. Use the composer on the right to schedule one.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Promoted Products</h5>
                </div>
                <div class="card-body">
                    @forelse ($campaign->products as $product)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ $product->name }}</span>
                            <span class="text-muted">{{ number_format((float) $product->selling_price, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No products attached.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">New Post</h5>
                </div>
                <div class="card-body">
                    @if ($socialAccounts->isEmpty())
                        <div class="alert alert-warning">No connected social accounts. <a
                                href="{{ route('social-accounts.create') }}">Connect one</a>.</div>
                    @else
                        @if ($campaign->ai_assist_enabled)
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="generateAiContent">
                                    <iconify-icon icon="solar:magic-stick-3-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Generate with AI
                                </button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('campaigns.posts.store', $campaign) }}" id="postForm">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Account</label>
                                <select name="social_account_id" class="form-select" required>
                                    @foreach ($socialAccounts as $a)
                                        <option value="{{ $a->id }}">{{ $a->platform->label() }} —
                                            {{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Product</label>
                                <select name="product_id" class="form-select" id="postProductId">
                                    <option value="">— None —</option>
                                    @foreach ($campaign->products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Caption</label>
                                <textarea name="caption" rows="4" class="form-control" required id="postCaption"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Hashtags (comma-separated)</label>
                                <input type="text" class="form-control" id="hashtagInput" placeholder="sale, fitness">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Landing URL</label>
                                <input type="url" name="landing_url" id="postLanding" class="form-control"
                                    value="{{ $campaign->default_landing_url }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Schedule for (optional)</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control">
                            </div>
                            <div id="hashtagContainer"></div>
                            <button class="btn btn-primary w-100">Save Post</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const hashInput = document.getElementById('hashtagInput');
                const hashContainer = document.getElementById('hashtagContainer');
                const syncHashtags = () => {
                    hashContainer.innerHTML = '';
                    (hashInput.value || '').split(',').map(s => s.trim()).filter(Boolean).forEach(tag => {
                        const i = document.createElement('input');
                        i.type = 'hidden';
                        i.name = 'hashtags[]';
                        i.value = tag;
                        hashContainer.appendChild(i);
                    });
                };
                hashInput && hashInput.addEventListener('input', syncHashtags);

                const aiBtn = document.getElementById('generateAiContent');
                if (aiBtn) {
                    aiBtn.addEventListener('click', async () => {
                        aiBtn.disabled = true;
                        aiBtn.textContent = 'Generating…';
                        try {
                            const productId = document.getElementById('postProductId').value;
                            const res = await fetch(
                            '{{ route('campaigns.generateContent', $campaign) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    product_id: productId || null
                                }),
                            });
                            const json = await res.json();
                            const content = json.data || {};
                            document.getElementById('postCaption').value = content.caption || '';
                            hashInput.value = (content.hashtags || []).join(', ');
                            syncHashtags();
                            aiBtn.textContent = 'Generated ✓';
                        } catch (e) {
                            aiBtn.textContent = 'Failed';
                        } finally {
                            setTimeout(() => {
                                aiBtn.disabled = false;
                                aiBtn.innerHTML =
                                    '<iconify-icon icon="solar:magic-stick-3-bold-duotone" class="align-middle me-1"></iconify-icon> Generate with AI';
                            }, 2000);
                        }
                    });
                }
            });
        </script>
    @endpush
@endsection
