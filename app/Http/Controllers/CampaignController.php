<?php

namespace App\Http\Controllers;

use App\Actions\Campaign\GenerateCampaignContent;
use App\Actions\Campaign\PublishCampaignPost;
use App\Actions\Campaign\ScheduleCampaignPost;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Enums\SocialPlatform;
use App\Http\Requests\GenerateCampaignContentRequest;
use App\Http\Requests\StoreCampaignPostRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SocialAccount;
use App\Services\CampaignService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CampaignService $campaigns) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $filters = $request->only(['search', 'status', 'shop_id', 'channel', 'campaign_type']);
        $campaigns = $this->campaigns->getPaginated($filters);
        $statistics = $this->campaigns->getStatistics($request->integer('shop_id') ?: null);

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'statistics' => $statistics,
            'shops' => Shop::active()->orderBy('name')->get(['id', 'name']),
            'statuses' => CampaignStatus::cases(),
            'channels' => MarketingChannel::cases(),
            'types' => CampaignType::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.create', [
            'shops' => Shop::active()->orderBy('name')->get(['id', 'name']),
            'types' => CampaignType::cases(),
            'channels' => MarketingChannel::cases(),
            'statuses' => CampaignStatus::cases(),
            'products' => Product::active()->orderBy('name')->limit(200)->get(['id', 'name', 'selling_price']),
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        $campaign = $this->campaigns->create($request->validated());

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign created.');
    }

    public function show(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $campaign->load(['shop', 'products', 'posts.socialAccount', 'posts.product', 'creator']);

        $socialAccounts = SocialAccount::active()
            ->where('shop_id', $campaign->shop_id)
            ->get();

        return view('campaigns.show', [
            'campaign' => $campaign,
            'socialAccounts' => $socialAccounts,
            'platforms' => SocialPlatform::cases(),
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        $campaign->load(['products']);

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'shops' => Shop::active()->orderBy('name')->get(['id', 'name']),
            'types' => CampaignType::cases(),
            'channels' => MarketingChannel::cases(),
            'statuses' => CampaignStatus::cases(),
            'products' => Product::active()->orderBy('name')->limit(200)->get(['id', 'name', 'selling_price']),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $campaign = $this->campaigns->update($campaign, $request->validated());

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $this->campaigns->delete($campaign);

        return redirect()
            ->route('campaigns.index')
            ->with('success', 'Campaign deleted.');
    }

    public function start(Campaign $campaign): RedirectResponse
    {
        $this->authorize('publish', $campaign);
        $this->campaigns->start($campaign);

        return back()->with('success', 'Campaign started.');
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->authorize('publish', $campaign);
        $this->campaigns->pause($campaign);

        return back()->with('success', 'Campaign paused.');
    }

    public function complete(Campaign $campaign): RedirectResponse
    {
        $this->authorize('publish', $campaign);
        $this->campaigns->complete($campaign);

        return back()->with('success', 'Campaign completed.');
    }

    public function metrics(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);
        $campaign->load(['posts']);

        return view('campaigns.metrics', compact('campaign'));
    }

    public function roi(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('campaigns.roi', compact('campaign'));
    }

    public function active(): View
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::active()->with(['shop'])->latest()->paginate(15);

        return view('campaigns.active', compact('campaigns'));
    }

    public function performance(): View
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::with('shop')->latest()->limit(50)->get();

        return view('campaigns.performance', compact('campaigns'));
    }

    public function byChannel(): View
    {
        $this->authorize('viewAny', Campaign::class);

        $byChannel = Campaign::query()
            ->selectRaw('channel, COUNT(*) as total, SUM(spent) as total_spent, SUM(actual_revenue) as total_revenue')
            ->groupBy('channel')
            ->get();

        return view('campaigns.by-channel', compact('byChannel'));
    }

    /**
     * Generate AI-assisted content for the campaign (optionally targeted at a product).
     */
    public function generateContent(GenerateCampaignContentRequest $request, Campaign $campaign, GenerateCampaignContent $action): JsonResponse
    {
        $this->authorize('update', $campaign);

        $product = null;
        if ($request->filled('product_id')) {
            $product = Product::findOrFail($request->integer('product_id'));
        }

        $content = $action->handle($campaign, $product, $request->only([
            'platform',
            'provider',
            'tone',
            'language',
            'extra_instructions',
        ]));

        return response()->json([
            'data' => $content,
        ]);
    }

    /**
     * Create or schedule a campaign post.
     */
    public function storePost(StoreCampaignPostRequest $request, Campaign $campaign, ScheduleCampaignPost $action): RedirectResponse
    {
        $this->authorize('publish', $campaign);

        $account = SocialAccount::where('shop_id', $campaign->shop_id)
            ->findOrFail($request->integer('social_account_id'));

        $product = $request->filled('product_id')
            ? Product::find($request->integer('product_id'))
            : null;

        $scheduledAt = $request->filled('scheduled_at')
            ? Carbon::parse($request->input('scheduled_at'))
            : null;

        $action->handle(
            campaign: $campaign,
            account: $account,
            content: [
                'caption' => $request->input('caption'),
                'hashtags' => $request->input('hashtags', []),
                'call_to_action' => $request->input('call_to_action'),
                'media_urls' => $request->input('media_urls', []),
                'landing_url' => $request->input('landing_url'),
                'ai_generated' => (bool) $request->boolean('ai_generated'),
                'ai_provider' => $request->input('ai_provider'),
                'ai_model' => $request->input('ai_model'),
            ],
            scheduledAt: $scheduledAt,
            product: $product,
        );

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', $scheduledAt ? 'Post scheduled.' : 'Post saved as draft.');
    }

    /**
     * Publish a campaign post immediately (manual override).
     */
    public function publishPost(Campaign $campaign, string $post, PublishCampaignPost $action): RedirectResponse
    {
        $this->authorize('publish', $campaign);

        $campaignPost = $campaign->posts()->where('uuid', $post)->firstOrFail();

        $result = $action->handle($campaignPost);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
