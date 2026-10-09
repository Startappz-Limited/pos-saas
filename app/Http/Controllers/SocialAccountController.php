<?php

namespace App\Http\Controllers;

use App\Actions\Social\ConnectSocialAccount;
use App\Enums\SocialPlatform;
use App\Http\Requests\StoreSocialAccountRequest;
use App\Models\Shop;
use App\Models\SocialAccount;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialAccountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ConnectSocialAccount $connect) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SocialAccount::class);

        $accounts = SocialAccount::query()
            ->with('shop')
            ->when($request->filled('shop_id'), fn($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('platform'), fn($q) => $q->where('platform', $request->string('platform')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('social-accounts.index', [
            'accounts' => $accounts,
            'shops' => Shop::active()->orderBy('name')->get(['id', 'name']),
            'platforms' => SocialPlatform::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SocialAccount::class);

        return view('social-accounts.create', [
            'shops' => Shop::active()->orderBy('name')->get(['id', 'name']),
            'platforms' => SocialPlatform::cases(),
        ]);
    }

    public function store(StoreSocialAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', SocialAccount::class);

        $shop = Shop::findOrFail($request->integer('shop_id'));
        $platform = SocialPlatform::from($request->string('platform')->value());

        $this->connect->handle($shop, $platform, $this->payload($request));

        return redirect()
            ->route('social-accounts.index')
            ->with('success', 'Social account connected.');
    }

    public function edit(SocialAccount $socialAccount): View
    {
        $this->authorize('update', $socialAccount);

        return view('social-accounts.edit', [
            'account' => $socialAccount,
            'shops' => Shop::active()->orderBy('name')->get(['id', 'name']),
            'platforms' => SocialPlatform::cases(),
        ]);
    }

    public function update(StoreSocialAccountRequest $request, SocialAccount $socialAccount): RedirectResponse
    {
        $this->authorize('update', $socialAccount);

        $shop = Shop::findOrFail($request->integer('shop_id'));
        $platform = SocialPlatform::from($request->string('platform')->value());

        $this->connect->handle($shop, $platform, $this->payload($request));

        return redirect()
            ->route('social-accounts.index')
            ->with('success', 'Social account updated.');
    }

    /**
     * Translate the flat request payload into the shape expected by
     * ConnectSocialAccount (credentials nested inside a sub-array).
     *
     * @return array<string, mixed>
     */
    protected function payload(StoreSocialAccountRequest $request): array
    {
        $data = $request->validated();

        $data['credentials'] = array_filter([
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        unset($data['access_token'], $data['refresh_token']);

        return $data;
    }

    public function destroy(SocialAccount $socialAccount): RedirectResponse
    {
        $this->authorize('delete', $socialAccount);

        $socialAccount->delete();

        return redirect()
            ->route('social-accounts.index')
            ->with('success', 'Social account disconnected.');
    }
}
