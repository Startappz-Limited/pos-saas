@php
    $selectedProductIds = $campaign?->products->pluck('id')->all() ?? [];
    $aiSettings = $campaign?->ai_settings ?? [];
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Campaign Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                            value="{{ old('name', $campaign?->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $campaign?->description) }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Shop <span class="text-danger">*</span></label>
                            <select name="shop_id" class="form-select" required>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected(old('shop_id', $campaign?->shop_id) == $shop->id)>{{ $shop->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="campaign_type" class="form-select">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}" @selected(old('campaign_type', $campaign?->campaign_type?->value) === $t->value)>{{ $t->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Channel</label>
                            <select name="channel" class="form-select">
                                @foreach ($channels as $c)
                                    <option value="{{ $c->value }}" @selected(old('channel', $campaign?->channel?->value) === $c->value)>{{ $c->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Budget</label>
                            <input type="number" step="0.01" name="budget" class="form-control"
                                value="{{ old('budget', $campaign?->budget ?? 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" class="form-control" maxlength="3"
                                value="{{ old('currency', $campaign?->currency ?? 'KES') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required
                                value="{{ old('start_date', optional($campaign?->start_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required
                                value="{{ old('end_date', optional($campaign?->end_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}" @selected(old('status', $campaign?->status?->value ?? 'draft') === $s->value)>
                                        {{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Promo Code</label>
                            <input type="text" name="promo_code" class="form-control"
                                value="{{ old('promo_code', $campaign?->promo_code) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Landing URL</label>
                            <input type="url" name="default_landing_url" class="form-control"
                                value="{{ old('default_landing_url', $campaign?->default_landing_url) }}"
                                placeholder="https://shop.example.com">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Products</h5>
                </div>
                <div class="card-body">
                    <label class="form-label">Promote products (Ctrl/Cmd-click for multi-select)</label>
                    <select name="product_ids[]" class="form-select" multiple size="8">
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(in_array($product->id, old('product_ids', $selectedProductIds)))>
                                {{ $product->name }} — {{ number_format((float) $product->selling_price, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">AI &amp; Auto-Posting</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="ai_assist_enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="ai_assist_enabled" value="1"
                            id="aiAssistEnabled" @checked(old('ai_assist_enabled', $campaign?->ai_assist_enabled))>
                        <label class="form-check-label" for="aiAssistEnabled">Enable AI content generation</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="auto_post_enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="auto_post_enabled" value="1"
                            id="autoPostEnabled" @checked(old('auto_post_enabled', $campaign?->auto_post_enabled))>
                        <label class="form-check-label" for="autoPostEnabled">Auto-post to social media</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">AI Provider</label>
                        <select name="ai_settings[provider]" class="form-select">
                            @foreach (\App\Enums\AiProvider::cases() as $p)
                                <option value="{{ $p->value }}" @selected(old('ai_settings.provider', $aiSettings['provider'] ?? config('ai.default')) === $p->value)>
                                    {{ $p->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tone</label>
                        <input type="text" name="ai_settings[tone]" class="form-control"
                            value="{{ old('ai_settings.tone', $aiSettings['tone'] ?? config('ai.defaults.tone')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Language</label>
                        <input type="text" name="ai_settings[language]" class="form-control"
                            value="{{ old('ai_settings.language', $aiSettings['language'] ?? config('ai.defaults.language')) }}">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <iconify-icon icon="solar:diskette-bold-duotone" class="align-middle me-1"></iconify-icon>
                        Save Campaign
                    </button>
                    <a href="{{ route('campaigns.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
