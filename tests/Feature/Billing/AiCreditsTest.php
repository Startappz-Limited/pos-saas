<?php

use App\Actions\Billing\RecordManualPayment;
use App\Exceptions\FeatureUnavailable;
use App\Exceptions\InsufficientAiCredit;
use App\Models\AiBlockedWork;
use App\Models\AiUsageEvent;
use App\Models\Campaign;
use App\Models\PlatformPayment;
use App\Notifications\AiCreditExhausted;
use App\Notifications\AiWorkReady;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiContentGenerator;
use App\Services\Billing\AiCredits;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TwoBusinesses;

/**
 * Prepaid, metered AI (Phase 4, decisions 4.6 and 4.9): each call is recorded
 * and charged, refused at zero balance, can't overdraw, and the work that was
 * waiting runs after a top-up.
 */
class FakeAiDriver implements AiContentGenerator
{
    public int $calls = 0;

    public ?array $usage = ['prompt_tokens' => 400, 'completion_tokens' => 600];

    public bool $fails = false;

    public function name(): string
    {
        return 'fake';
    }

    public function generateCampaignContent(array $context): array
    {
        $this->calls++;

        if ($this->fails) {
            throw new RuntimeException('provider down');
        }

        return [
            'caption' => 'Hello', 'hashtags' => ['a'], 'call_to_action' => 'Buy', 'provider' => 'fake', 'model' => 'fake-1',
            'raw' => $this->usage === null ? [] : ['usage' => $this->usage],
        ];
    }
}

beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }
    Storage::fake('tenant');
    Storage::fake('public');
    Notification::fake();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->tenant = app(TenantContext::class);
    $this->credits = app(AiCredits::class);

    $this->driver = new FakeAiDriver;
    config(['ai.drivers.fake' => ['driver' => 'fake'], 'ai.default' => 'fake']);
    $this->manager = new AiManager;
    $this->manager->extend('fake', fn () => $this->driver);
    app()->instance(AiManager::class, $this->manager);

    subscribe($this->a->business, 'tier-3', ['shop_count' => 5]);
    $this->tenant->set($this->a->business);
});

function balanceOf($test): float
{
    return $test->credits->balance($test->a->business->id);
}

it('charges the tokens a call used and records it', function () {
    $this->credits->topUp($this->a->business->id, 1000);

    $result = $this->manager->driver('fake')->generateCampaignContent([]);

    // 1,000 tokens at 10 credits per 1,000.
    expect($result['caption'])->toBe('Hello')
        ->and(balanceOf($this))->toBe(990.0);

    $event = AiUsageEvent::query()->where('kind', 'call')->sole();
    expect($event->business_id)->toBe($this->a->business->id)
        ->and($event->provider)->toBe('fake')
        ->and($event->model)->toBe('fake-1')
        ->and($event->tokens_in)->toBe(400)
        ->and($event->tokens_out)->toBe(600)
        ->and((float) $event->credits)->toBe(10.0);
});

it('charges the per-call price when the provider reports no tokens', function () {
    $this->credits->topUp($this->a->business->id, 100);
    $this->driver->usage = null;

    $this->manager->driver('fake')->generateCampaignContent([]);

    expect(balanceOf($this))->toBe(80.0);
});

it('refuses at zero balance without ever reaching the provider', function () {
    expect(fn () => $this->manager->driver('fake')->generateCampaignContent([]))->toThrow(InsufficientAiCredit::class)
        ->and($this->driver->calls)->toBe(0);
});

it('cannot take the balance below zero', function () {
    // 25 credits: one 20-credit reservation fits, a second does not.
    $this->credits->topUp($this->a->business->id, 25);

    $this->credits->reserve($this->a->business->id, 20);

    expect(fn () => $this->credits->reserve($this->a->business->id, 20))->toThrow(InsufficientAiCredit::class)
        ->and(balanceOf($this))->toBe(5.0);
});

it('takes an excess over the reservation only as far as the balance reaches', function () {
    $this->credits->topUp($this->a->business->id, 30);
    $this->credits->reserve($this->a->business->id, 20);

    // The call really cost 50: 10 left to take, no more.
    $charged = $this->credits->settle($this->a->business->id, 20, 50);

    expect($charged)->toBe(30.0)->and(balanceOf($this))->toBe(0.0);
});

it('releases the reservation when the provider fails, and records the failure', function () {
    $this->credits->topUp($this->a->business->id, 100);
    $this->driver->fails = true;

    expect(fn () => $this->manager->driver('fake')->generateCampaignContent([]))->toThrow(RuntimeException::class)
        ->and(balanceOf($this))->toBe(100.0)
        ->and(AiUsageEvent::query()->where('kind', 'call')->value('status'))->toBe('failed');
});

it('keeps one business\'s credit apart from another\'s', function () {
    subscribe($this->b->business, 'tier-3', ['shop_count' => 5]);
    $this->credits->topUp($this->b->business->id, 500);

    expect(fn () => $this->manager->driver('fake')->generateCampaignContent([]))->toThrow(InsufficientAiCredit::class)
        ->and($this->credits->balance($this->b->business->id))->toBe(500.0);
});

it('needs a plan that includes AI', function () {
    $this->tenant->forget();
    subscribe($this->b->business, 'tier-1', ['shop_count' => 5]);
    $this->tenant->set($this->b->business);
    $this->credits->topUp($this->b->business->id, 500);

    expect(fn () => $this->manager->driver('fake')->generateCampaignContent([]))->toThrow(FeatureUnavailable::class)
        ->and($this->driver->calls)->toBe(0)
        ->and($this->credits->balance($this->b->business->id))->toBe(500.0);
});

it('does not meter a business with no subscription, or the null driver', function () {
    $this->tenant->set($this->b->business);

    expect($this->manager->driver('fake')->generateCampaignContent([])['caption'])->toBe('Hello');

    $this->tenant->set($this->a->business);
    config(['ai.drivers.null' => ['driver' => 'null']]);
    expect($this->manager->driver('null')->generateCampaignContent([])['caption'])->not->toBeEmpty();
});

describe('work waiting for credit', function () {
    beforeEach(function () {
        $this->campaign = Campaign::factory()->create(['shop_id' => $this->a->shops[0]->id]);
        $this->tenant->forget();
    });

    it('saves a refused request, tells the owners once, and runs it after a top-up', function () {
        $url = route('campaigns.generateContent', $this->campaign);

        $this->actingAs($this->a->owner)->postJson($url, ['platform' => 'instagram'])
            ->assertStatus(402)->assertJsonPath('code', 'ai_credit_exhausted')->assertJsonStructure(['work']);
        $this->actingAs($this->a->owner)->postJson($url, ['platform' => 'facebook'])->assertStatus(402);

        $this->tenant->run($this->a->business, function (): void {
            expect(AiBlockedWork::query()->where('status', 'waiting')->count())->toBe(2);
        });
        Notification::assertSentToTimes($this->a->owner, AiCreditExhausted::class, 1);
        Notification::assertNotSentTo($this->b->owner, AiCreditExhausted::class);

        // KES 500 buys 5,000 credits; the queue is synchronous in tests, so both run.
        app(RecordManualPayment::class)->execute($this->a->business, PlatformPayment::AI_TOPUP, 500);

        $this->tenant->run($this->a->business, function (): void {
            expect(AiBlockedWork::query()->where('status', 'done')->count())->toBe(2)
                ->and(AiBlockedWork::query()->where('status', 'done')->first()->result['caption'])->toBe('Hello')
                ->and(balanceOf($this))->toBeLessThan(5000.0);
        });
        Notification::assertSentTo($this->a->owner, AiWorkReady::class);
        expect($this->driver->calls)->toBe(2);
    });

    it('re-arms the empty notice after a top-up', function () {
        $url = route('campaigns.generateContent', $this->campaign);
        $this->actingAs($this->a->owner)->postJson($url)->assertStatus(402);

        app(RecordManualPayment::class)->execute($this->a->business, PlatformPayment::AI_TOPUP, 500);
        $this->tenant->run($this->a->business, fn () => $this->credits->reserve($this->a->business->id, 4990.0));

        $this->actingAs($this->a->owner)->postJson($url)->assertStatus(402);

        Notification::assertSentToTimes($this->a->owner, AiCreditExhausted::class, 2);
    });
});

it('shows a top-up under the minimum as a field error, on the web and /api/v1', function () {
    $this->tenant->forget();

    $this->actingAs($this->a->owner)->from(route('billing.show'))
        ->post(route('billing.ai-topup'), ['phone' => '0712345678', 'amount' => 499])
        ->assertRedirect(route('billing.show'))->assertSessionHasErrors('amount');

    $this->tenant->forget();
    $token = $this->postJson(route('api.v1.auth.sign-in'), ['email' => $this->a->owner->email, 'password' => 'password', 'device_name' => 'Till'])
        ->json('data.token');
    app('auth')->forgetGuards();
    $this->tenant->forget();
    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('api.v1.billing.ai-topup'), ['phone' => '0712345678', 'amount' => 499])
        ->assertUnprocessable()->assertJsonValidationErrors('amount');

    expect($this->tenant->run($this->a->business, fn () => PlatformPayment::count()))->toBe(0);
});
