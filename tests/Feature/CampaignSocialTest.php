<?php

use App\Actions\Campaign\GenerateCampaignContent;
use App\Actions\Campaign\PublishCampaignPost;
use App\Actions\Campaign\ScheduleCampaignPost;
use App\Actions\Social\ConnectSocialAccount;
use App\Enums\PostStatus;
use App\Enums\SocialPlatform;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('ai.default', 'null');
});

it('generates campaign content using the null AI driver', function () {
    $shop = Shop::factory()->create();
    $campaign = Campaign::factory()->withAi()->create([
        'shop_id' => $shop->id,
        'default_landing_url' => 'https://shop.example.com',
    ]);
    $product = Product::factory()->create([
        'name' => 'Whey Protein 1kg',
    ]);

    $content = app(GenerateCampaignContent::class)->handle($campaign, $product);

    expect($content)
        ->toHaveKeys(['caption', 'hashtags', 'call_to_action', 'provider', 'landing_url'])
        ->and($content['caption'])->toBeString()->not->toBeEmpty()
        ->and($content['hashtags'])->toBeArray()
        ->and($content['landing_url'])->toBe('https://shop.example.com');
});

it('schedules a campaign post and dispatches a delayed publish job', function () {
    Queue::fake();

    $shop = Shop::factory()->create();
    $campaign = Campaign::factory()->create(['shop_id' => $shop->id]);
    $account = SocialAccount::factory()->facebook()->create(['shop_id' => $shop->id]);
    $when = now()->addHour()->startOfSecond();

    app(ScheduleCampaignPost::class)->handle(
        campaign: $campaign,
        account: $account,
        content: [
            'caption' => 'Hello world',
            'hashtags' => ['fitness'],
            'landing_url' => 'https://shop.example.com',
        ],
        scheduledAt: $when,
    );

    $post = CampaignPost::where('campaign_id', $campaign->id)->firstOrFail();

    expect($post->status)->toBe(PostStatus::SCHEDULED)
        ->and($post->scheduled_at?->equalTo($when))->toBeTrue();

    Queue::assertPushed(PublishCampaignPostJob::class, fn(PublishCampaignPostJob $job) => $job->campaignPostId === $post->id);
});

it('publishes a campaign post using the simulated facebook driver', function () {
    $shop = Shop::factory()->create();
    $campaign = Campaign::factory()->create(['shop_id' => $shop->id]);
    $account = SocialAccount::factory()->facebook()->create([
        'shop_id' => $shop->id,
        'external_account_id' => '1234567890',
    ]);

    $post = CampaignPost::factory()->create([
        'campaign_id' => $campaign->id,
        'shop_id' => $shop->id,
        'social_account_id' => $account->id,
        'platform' => SocialPlatform::FACEBOOK,
        'status' => PostStatus::DRAFT,
        'caption' => 'Test caption',
    ]);

    $result = app(PublishCampaignPost::class)->handle($post->fresh());

    expect($result['success'])->toBeTrue();
    expect($post->fresh()->status)->toBe(PostStatus::PUBLISHED);
    expect($post->fresh()->external_post_id)->not->toBeNull();
});

it('connects a social account and encrypts credentials at rest', function () {
    $shop = Shop::factory()->create();

    $account = app(ConnectSocialAccount::class)->handle($shop, SocialPlatform::INSTAGRAM, [
        'account_name' => 'Demo IG',
        'external_account_id' => 'ig_12345',
        'credentials' => ['access_token' => 'fake_token_demo'],
    ]);

    expect($account->shop_id)->toBe($shop->id)
        ->and($account->platform)->toBe(SocialPlatform::INSTAGRAM)
        ->and($account->is_active)->toBeTrue()
        ->and($account->getCredential('access_token'))->toBe('fake_token_demo');

    // Stored ciphertext should not contain the plaintext token.
    expect($account->credentials)->not->toContain('fake_token_demo');

    // And it should round-trip through Laravel's Crypt facade.
    $decrypted = json_decode(Crypt::decryptString($account->credentials), true);
    expect($decrypted['access_token'])->toBe('fake_token_demo');
});
