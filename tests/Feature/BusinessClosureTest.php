<?php

use App\Enums\BusinessExportStatus;
use App\Enums\PostStatus;
use App\Models\Business;
use App\Models\BusinessExport;
use App\Models\CampaignPost;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\BusinessClosureScheduled;
use App\Notifications\BusinessClosureCodeNotification;
use App\Notifications\BusinessExportReady;
use App\Support\BusinessClosureCode;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Phase 2 of account deletion, up to (not including) the purge: the business
 * owner downloads an export of everything, then closes the business. It is
 * frozen for the grace period, when only the owner can sign in, to download
 * the final export or cancel.
 */
beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    Storage::fake(BusinessExport::DISK);
    Notification::fake();

    $this->business = Business::factory()->create(['name' => 'Acme Fitness']);
    $this->owner = User::factory()->create(['business_id' => $this->business->id, 'email' => 'owner@example.test']);
    $this->owner->assignRole(Role::ADMIN);
    $this->business->update(['owner_id' => $this->owner->id]);
    $this->shop = Shop::factory()->create(['business_id' => $this->business->id, 'manager_id' => $this->owner->id]);

    $this->coAdmin = User::factory()->create(['business_id' => $this->business->id]);
    $this->coAdmin->assignRole(Role::ADMIN);

    $this->staff = User::factory()->create(['business_id' => $this->business->id]);
    $this->staff->assignRole('cashier');
    $this->staff->shops()->attach($this->shop);

    Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Own Customer', 'notes' => '=HYPERLINK("http://evil.test")']);

    $this->otherBusiness = Business::factory()->create();
    $otherShop = Shop::factory()->create(['business_id' => $this->otherBusiness->id]);
    Customer::factory()->create(['shop_id' => $otherShop->id, 'name' => 'Someone Elses Customer']);
});

/**
 * Requests an export as the owner and returns it (the queue is sync in tests).
 */
function readyExport(bool $downloaded = true): BusinessExport
{
    test()->actingAs(test()->owner)->post(route('business.exports.store'))->assertRedirect(route('profile.edit'));

    $export = test()->business->exports()->latest('id')->firstOrFail();

    if ($downloaded) {
        test()->get(route('business.exports.download', $export))->assertOk();
    }

    return $export->fresh();
}

/**
 * @return array<string, string> file name => contents
 */
function exportFiles(BusinessExport $export): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk(BusinessExport::DISK)->path($export->path));
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();

    return $files;
}

/**
 * Step 1: email + password. Returns the response; the code is in the email.
 */
function requestClosureCode(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('business.close.code'), $overrides + [
        'email' => 'owner@example.test',
        'password' => 'password',
        'export_kept' => '1',
    ]);
}

/**
 * The code in the latest closure-code email to the owner.
 */
function emailedClosureCode(): string
{
    $sent = Notification::sent(test()->owner, BusinessClosureCodeNotification::class);
    expect($sent)->not->toBeEmpty();

    return $sent->last()->code;
}

/**
 * Both steps: email + password, then the emailed code.
 */
function closeBusiness(): TestResponse
{
    requestClosureCode()->assertSessionHasNoErrors();

    return test()->actingAs(test()->owner)->post(route('business.close'), ['code' => emailedClosureCode()]);
}

describe('exporting the data', function () {
    it('builds a ZIP of the business and emails the owner a link', function () {
        $export = readyExport(downloaded: false);

        expect($export->status)->toBe(BusinessExportStatus::READY)
            ->and(Storage::disk(BusinessExport::DISK)->exists($export->path))->toBeTrue()
            ->and($export->expires_at->isFuture())->toBeTrue();

        Notification::assertSentTo($this->owner, BusinessExportReady::class, function (BusinessExportReady $notification) use ($export) {
            return str_contains($notification->toMail($this->owner)->actionUrl, route('business.exports.download', $export));
        });
    });

    it('holds only this business\'s data, without secrets, and safe to open in a spreadsheet', function () {
        $files = exportFiles(readyExport(downloaded: false));

        expect($files)->toHaveKeys(['business.json', 'README.txt', 'shops.csv', 'staff.csv', 'customers.csv', 'products.csv', 'sales.csv'])
            ->and($files['customers.csv'])->toContain('Own Customer')
            ->not->toContain('Someone Elses Customer')
            ->toContain("'=HYPERLINK")
            ->and($files['staff.csv'])->toContain('owner@example.test')
            ->and(str_getcsv(strtok($files['staff.csv'], "\n")))->not->toContain('password')->not->toContain('remember_token')
            ->and(str_getcsv(strtok($files['shops.csv'], "\n")))->not->toContain('settings')
            ->and(json_decode($files['business.json'], true)['record_counts']['customers'])->toBe(1);
    });

    it('records the download', function () {
        expect(readyExport()->downloaded_at)->not->toBeNull();
    });

    it('is for the owner alone', function (string $who) {
        $user = match ($who) {
            'co-admin' => $this->coAdmin,
            'staff' => $this->staff,
            'super-admin' => tap(User::factory()->create(['business_id' => null]))->assignRole(Role::SUPER_ADMIN),
        };
        $export = readyExport();

        $this->actingAs($user)->get(route('business.exports.download', $export))->assertForbidden();

        if ($who !== 'super-admin') {
            $this->actingAs($user)->post(route('business.exports.store'))->assertForbidden();
        }
    })->with(['co-admin', 'staff', 'super-admin']);
});

describe('closing the business', function () {
    it('shows the owner the close card, and others not', function () {
        $this->actingAs($this->owner)->get(route('profile.edit'))
            ->assertOk()->assertSee('Close business')->assertSee('Prepare export');

        $this->actingAs($this->coAdmin)->get(route('profile.edit'))
            ->assertOk()->assertDontSee('Prepare export')->assertSee('Deactivate my account');
    });

    it('needs a downloaded export first', function () {
        readyExport(downloaded: false);

        requestClosureCode()->assertSessionHasErrorsIn('businessClosure', 'export_kept');
        Notification::assertNotSentTo($this->owner, BusinessClosureCodeNotification::class);

        expect($this->business->fresh()->isClosing())->toBeFalse();
    });

    it('needs the owner\'s email and password before sending a code', function (array $input, string $field) {
        readyExport();

        requestClosureCode($input)->assertSessionHasErrorsIn('businessClosure', $field);

        Notification::assertNotSentTo($this->owner, BusinessClosureCodeNotification::class);
        expect(BusinessClosureCode::pending($this->business))->toBeFalse();
    })->with([
        'someone else\'s email' => [['email' => 'staff@example.test'], 'email'],
        'wrong password' => [['password' => 'nope'], 'password'],
        'export not confirmed' => [['export_kept' => ''], 'export_kept'],
    ]);

    it('emails a code and then asks for it', function () {
        readyExport();

        requestClosureCode()->assertRedirect(route('profile.edit'))->assertSessionHas('closure-code-sent');

        expect(emailedClosureCode())->toMatch('/^\d{6}$/');
        $this->get(route('profile.edit'))->assertSee('Enter it to close the business')->assertDontSee('Your password');
        expect($this->business->fresh()->isClosing())->toBeFalse();
    });

    it('does not close with a wrong code, and gives up after too many', function () {
        readyExport();
        requestClosureCode();
        $code = emailedClosureCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i < BusinessClosureCode::MAX_ATTEMPTS; $i++) {
            $this->post(route('business.close'), ['code' => $wrong])->assertSessionHasErrorsIn('businessClosure', 'code');
        }
        $this->post(route('business.close'), ['code' => $wrong])->assertSessionHasErrorsIn('businessClosure', 'email');

        // Used up: even the right code no longer works
        $this->post(route('business.close'), ['code' => $code])->assertSessionHasErrorsIn('businessClosure', 'email');
        expect($this->business->fresh()->isClosing())->toBeFalse();
    });

    it('does not accept an expired code', function () {
        readyExport();
        requestClosureCode();
        $code = emailedClosureCode();

        $this->travel(BusinessClosureCode::MINUTES + 1)->minutes();

        $this->post(route('business.close'), ['code' => $code])->assertSessionHasErrorsIn('businessClosure', 'email');
        expect($this->business->fresh()->isClosing())->toBeFalse();
    });

    it('limits how often a new code is sent, and the newest code wins', function () {
        readyExport();
        requestClosureCode();
        $first = emailedClosureCode();

        $this->post(route('business.close.resend'))->assertSessionHasErrorsIn('businessClosure', 'code');
        Notification::assertSentToTimes($this->owner, BusinessClosureCodeNotification::class, 1);

        $this->travel(BusinessClosureCode::RESEND_SECONDS + 1)->seconds();
        $this->post(route('business.close.resend'))->assertSessionHasNoErrors();
        $second = emailedClosureCode();

        if ($first !== $second) {
            $this->post(route('business.close'), ['code' => $first])->assertSessionHasErrorsIn('businessClosure', 'code');
        }
        $this->post(route('business.close'), ['code' => $second])->assertRedirect(route('business.closing'));
    });

    it('freezes the business for 30 days and sends a final export', function () {
        readyExport();

        closeBusiness()->assertRedirect(route('business.closing'));

        $business = $this->business->fresh();
        expect($business->isClosing())->toBeTrue()
            ->and((int) round($business->closing_requested_at->diffInDays($business->purge_after)))->toBe(Business::GRACE_DAYS)
            ->and($business->closing_requested_by)->toBe($this->owner->id)
            ->and($business->finalExport->isReady())->toBeTrue()
            ->and($business->finalExport->expires_at->equalTo($business->purge_after))->toBeTrue();

        Notification::assertSentTo($this->owner, BusinessClosureScheduled::class);
        Notification::assertSentToTimes($this->owner, BusinessExportReady::class, 2);
    });

    it('cannot be closed by a co-admin or a super-admin', function (string $who) {
        readyExport();
        $user = $who === 'co-admin'
            ? $this->coAdmin
            : tap(User::factory()->create(['business_id' => null]))->assignRole(Role::SUPER_ADMIN);

        $this->actingAs($user)->post(route('business.close.code'), [
            'email' => $user->email, 'password' => 'password', 'export_kept' => '1',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('business.close'), ['code' => '123456'])->assertForbidden();

        expect($this->business->fresh()->isClosing())->toBeFalse();
    })->with(['co-admin', 'super-admin']);
});

describe('the grace period', function () {
    beforeEach(function () {
        readyExport();
        closeBusiness();
    });

    it('limits the owner to the closing page', function () {
        $this->actingAs($this->owner);

        $this->get(route('dashboard'))->assertRedirect(route('business.closing'));
        $this->get(route('profile.edit'))->assertRedirect(route('business.closing'));
        $this->get(route('business.closing'))->assertOk()->assertSee('Download final export');
        $this->get(route('business.exports.download', $this->business->fresh()->finalExport))->assertOk();
    });

    it('signs staff out and refuses them on the API', function () {
        $this->actingAs($this->staff)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/products')->assertForbidden()->assertJson(['success' => false, 'code' => 'business_closing']);
    });

    it('leaves other businesses alone', function () {
        $other = User::factory()->create(['business_id' => $this->otherBusiness->id]);
        $other->assignRole(Role::ADMIN);
        $this->otherBusiness->update(['owner_id' => $other->id]);

        $this->actingAs($other)->get(route('dashboard'))->assertOk();
    });

    it('ignores shop webhooks and scheduled posts', function () {
        Queue::fake();

        $this->postJson(route('webhooks.woocommerce', $this->shop), [], ['X-WC-Webhook-Topic' => 'order.created'])->assertOk();
        Queue::assertNothingPushed();

        $post = CampaignPost::factory()->create(['shop_id' => $this->shop->id, 'status' => PostStatus::SCHEDULED, 'scheduled_at' => now()->subMinute()]);
        expect(CampaignPost::withoutGlobalScopes()->duePublishing()->pluck('id'))->not->toContain($post->id);
    });

    it('can be cancelled by the owner, which lets everyone back in', function () {
        $finalExport = $this->business->fresh()->finalExport;

        $this->actingAs($this->owner)->post(route('business.closing.cancel'))->assertRedirect(route('dashboard'));

        expect($this->business->fresh()->isClosing())->toBeFalse()
            ->and(BusinessExport::find($finalExport->id))->toBeNull()
            ->and(Storage::disk(BusinessExport::DISK)->exists($finalExport->path))->toBeFalse();

        $this->actingAs($this->staff)->get(route('dashboard'))->assertOk();
    });
});

it('deletes expired export files', function () {
    $export = readyExport();
    $export->update(['expires_at' => now()->subMinute()]);

    $this->artisan('business-exports:prune')->assertSuccessful();

    expect(Storage::disk(BusinessExport::DISK)->exists($export->path))->toBeFalse()
        ->and($export->fresh()->status)->toBe(BusinessExportStatus::EXPIRED);
});
