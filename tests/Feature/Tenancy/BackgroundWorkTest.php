<?php

use App\Enums\PostStatus;
use App\Jobs\PublishCampaignPostJob;
use App\Models\BaileysSession;
use App\Models\Business;
use App\Models\CampaignPost;
use App\Models\Customer;
use App\Models\Shop;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TwoBusinesses;

/**
 * Background work acts for one business (stage 1c, decision 4.3): queued
 * jobs carry the business they were dispatched for, the scheduler's sweeps
 * run per business and skip suspended ones, and console commands act for the
 * business they name.
 */
class RecordsItsBusiness implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /** @var list<array{0: int|null, 1: int}> */
    public static array $seen = [];

    public function handle(): void
    {
        self::$seen[] = [app(TenantContext::class)->id(), Shop::count()];
    }
}

beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
    RecordsItsBusiness::$seen = [];
    app(TenantContext::class)->forget();
});

it('runs a queued job for the business it was dispatched for', function () {
    config(['queue.default' => 'database']);
    $a = $this->fixture->a;

    // A block body: a pending dispatch is queued when it's released, which
    // must happen while the business is still set.
    app(TenantContext::class)->run($a->business, function (): void {
        RecordsItsBusiness::dispatch();
    });
    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default', '--stop-when-empty' => true])->assertSuccessful();

    expect(RecordsItsBusiness::$seen)->toBe([[$a->business->id, count($a->shops)]]);
});

it('refuses tenant data to a queued job that carries no business', function () {
    config(['queue.default' => 'database']);

    RecordsItsBusiness::dispatch();
    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default', '--stop-when-empty' => true]);

    expect(RecordsItsBusiness::$seen)->toBe([]);
});

it('sweeps due campaign posts per business and skips a suspended business', function () {
    Queue::fake();
    $due = [];
    $suspended = Business::factory()->create(['status' => 'suspended']);

    foreach ([$this->fixture->a->business, $this->fixture->b->business, $suspended] as $business) {
        $due[$business->id] = app(TenantContext::class)->run($business, fn () => CampaignPost::factory()->create([
            'status' => PostStatus::SCHEDULED, 'scheduled_at' => now()->subMinute(),
        ])->id);
    }

    collect(app(Schedule::class)->events())->firstWhere('description', 'publish-due-campaign-posts')->run(app());

    $swept = Queue::pushed(PublishCampaignPostJob::class)->map(fn ($job) => $job->campaignPostId)->sort()->values()->all();
    expect($swept)->toBe([$due[$this->fixture->a->business->id], $due[$this->fixture->b->business->id]]);
});

it('runs a command for the business it names, and only that one', function () {
    $customers = [];
    foreach ([$this->fixture->a, $this->fixture->b] as $fixture) {
        $customers[] = app(TenantContext::class)->run($fixture->business, function () use ($fixture) {
            $customer = Customer::factory()->forShop($fixture->shops[0])->create(['phone' => '0712 000 111']);
            $customer->forceFill(['phone_normalized' => null])->saveQuietly();

            return $customer;
        });
    }

    $this->artisan('customers:normalize-phones', ['--apply' => true, '--business' => $this->fixture->a->business->uuid])->assertSuccessful();

    $normalized = fn (Customer $customer) => app(TenantContext::class)->run(
        $customer->business_id,
        fn () => Customer::whereKey($customer->id)->value('phone_normalized'),
    );
    expect($normalized($customers[0]))->toBe('254712000111')
        ->and($normalized($customers[1]))->toBeNull();
});

it('refuses to guess the business when several exist', function () {
    $this->artisan('customers:normalize-phones')
        ->expectsOutputToContain('Several businesses exist')
        ->assertFailed();
});

it('never takes another business\'s WhatsApp media for an orphan', function () {
    Storage::fake('public');
    $session = app(TenantContext::class)->run($this->fixture->b->business, fn () => BaileysSession::factory()->create(['shop_id' => $this->fixture->b->shops[0]->id]));
    Storage::disk('public')->put("baileys/{$session->uuid}/in/kept.jpg", 'b');
    Storage::disk('public')->put('baileys/'.Str::uuid().'/in/orphan.jpg', 'x');

    $this->artisan('baileys:prune-media', ['--orphans' => true, '--business' => $this->fixture->a->business->id])->assertSuccessful();

    expect(Storage::disk('public')->exists("baileys/{$session->uuid}/in/kept.jpg"))->toBeTrue()
        ->and(Storage::disk('public')->allFiles('baileys'))->toBe(["baileys/{$session->uuid}/in/kept.jpg"]);
});

it('keys cached values by business, and refuses a key with no business', function () {
    $key = fn ($business) => app(TenantContext::class)->run($business, fn () => app(TenantContext::class)->cacheKey('sms_settings'));

    expect($key($this->fixture->a->business))->not->toBe($key($this->fixture->b->business))
        ->and(fn () => app(TenantContext::class)->cacheKey('sms_settings'))->toThrow(MissingTenantContext::class);
});
