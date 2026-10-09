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
                    <h5 class="mb-0">{{ $account ? 'Update' : 'Connect' }} Social Account</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small">
                        Paste an access token from Meta for Developers / Google Ads API. Tokens are encrypted at rest.
                        Use a token starting with <code>fake_</code> to dry-run without calling the live API.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Shop <span class="text-danger">*</span></label>
                            <select name="shop_id" class="form-select" required>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected(old('shop_id', $account?->shop_id) == $shop->id)>{{ $shop->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Platform <span class="text-danger">*</span></label>
                            <select name="platform" class="form-select" required>
                                @foreach ($platforms as $p)
                                    <option value="{{ $p->value }}" @selected(old('platform', $account?->platform?->value) === $p->value)>{{ $p->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" class="form-control" required
                                value="{{ old('account_name', $account?->account_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">External Account / Page ID</label>
                            <input type="text" name="external_account_id" class="form-control"
                                value="{{ old('external_account_id', $account?->external_account_id) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username (optional)</label>
                            <input type="text" name="username" class="form-control"
                                value="{{ old('username', $account?->username) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Token Expires At</label>
                            <input type="datetime-local" name="token_expires_at" class="form-control"
                                value="{{ old('token_expires_at', optional($account?->token_expires_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Access Token <span class="text-danger">*</span></label>
                            <textarea name="access_token" rows="3" class="form-control" required placeholder="EAAB... or fake_token_demo">{{ old('access_token') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Refresh Token (optional)</label>
                            <textarea name="refresh_token" rows="2" class="form-control">{{ old('refresh_token') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button class="btn btn-primary">Save</button>
                    <a href="{{ route('social-accounts.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
