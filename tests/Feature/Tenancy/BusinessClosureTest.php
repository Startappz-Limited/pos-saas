<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\BusinessExport;
use App\Models\User;
use App\Notifications\BusinessExportReady;
use App\Notifications\OwnerConfirmationCode;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Canaries;
use Tests\Support\TwoBusinesses;

/**
 * Data export and business closure (stage 3): owners export; closing is
 * confirmed by an emailed code, makes the business unusable, emails the final
 * export, can be cancelled during the grace period, and ends in erasure.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }
    Storage::fake(TenantFiles::PRIVATE_DISK);
    Storage::fake(TenantFiles::PUBLIC_DISK);
    Notification::fake();

    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    Canaries::plant($this->a, 'Alpha');
    Canaries::plant($this->fixture->b, 'Bravo');
    app(TenantContext::class)->run($this->a->business, fn () => app(TenantFiles::class)->putIfRoom('whatsapp/media.jpg', 'private bytes'));
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
});

/**
 * The link in the "export ready" email sent to $owner.
 */
function exportLinkFor(User $owner): string
{
    $url = null;
    Notification::assertSentTo($owner, BusinessExportReady::class, function (BusinessExportReady $notification) use (&$url): bool {
        $url = $notification->downloadUrl();

        return true;
    });

    return $url;
}

/**
 * Asks for a closure code as $owner and returns it from the email.
 */
function closureCodeFor(User $owner): string
{
    test()->actingAs($owner)->post(route('business.closure.code'))->assertRedirect(route('business.data'));

    $code = null;
    Notification::assertSentTo($owner, OwnerConfirmationCode::class, function (OwnerConfirmationCode $notification) use (&$code): bool {
        $code = $notification->code();

        return true;
    });

    return $code;
}

/**
 * The files in a ZIP, by name.
 *
 * @return array<string, string>
 */
function zipContents(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();
    unlink($path);

    return $files;
}

it('exports a business\'s data for its owners, without secrets or the other business', function () {
    $this->actingAs($this->a->owner)->post(route('business.exports.store'))->assertRedirect(route('business.data'));

    $export = $this->tenant->run($this->a->business, fn () => BusinessExport::sole());
    expect($export->status)->toBe(BusinessExport::STATUS_READY);

    $url = exportLinkFor($this->a->owner);
    $this->tenant->forget();
    $response = $this->actingAs($this->a->owner)->get($url)->assertOk();
    $files = zipContents($response->streamedContent());

    expect($files)->toHaveKeys(['manifest.json', 'data/customers.jsonl', 'data/sales.jsonl', 'data/users.jsonl', 'audit/audit_logs.jsonl', 'files/private/whatsapp/media.jpg'])
        ->and($files['data/customers.jsonl'])->toContain('Alpha Customer')
        ->and(implode('', $files))->not->toContain('Bravo')
        ->and($files['data/users.jsonl'])->toContain($this->a->owner->email)
        ->and($files['data/users.jsonl'])->not->toContain('"password"')
        ->and(array_keys($files))->not->toContain('data/personal_access_tokens.jsonl')
        ->and(json_decode($files['manifest.json'], true)['business']['uuid'])->toBe($this->a->business->uuid);
});

it('lets only an owner of the business export or download', function () {
    $this->actingAs($this->a->cashier)->get(route('business.data'))->assertForbidden();
    $this->actingAs($this->a->cashier)->post(route('business.exports.store'))->assertForbidden();
    $this->actingAs($this->a->cashier)->post(route('business.closure.code'))->assertForbidden();

    $this->actingAs($this->a->owner)->post(route('business.exports.store'));
    $url = exportLinkFor($this->a->owner);
    $export = $this->tenant->run($this->a->business, fn () => BusinessExport::sole());
    $unsigned = route('business.exports.download', $export);

    foreach ([$this->a->cashier, $this->fixture->b->owner, $this->fixture->both] as $notOwner) {
        $this->tenant->forget();
        app('auth')->forgetGuards();
        $this->actingAs($notOwner)->get($url)->assertNotFound();
    }
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->a->owner)->get($unsigned)->assertForbidden();
});

it('closes a business with the emailed code: unusable, final export emailed, erasure scheduled', function () {
    $code = closureCodeFor($this->a->owner);

    $this->actingAs($this->a->owner)->post(route('business.closure.confirm'), ['code' => $code])
        ->assertRedirect(route('businesses.index'));

    $business = $this->a->business->fresh();
    $closure = BusinessClosure::sole();
    expect($business->status)->toBe(Business::STATUS_CLOSING)
        ->and($closure->business_id)->toBe($business->id)
        ->and($closure->requested_by)->toBe($this->a->owner->id)
        ->and($closure->delete_after->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($this->tenant->run($business, fn () => BusinessExport::sole()->purpose))->toBe(BusinessExport::PURPOSE_CLOSURE);
    Notification::assertSentTo($this->a->owner, BusinessExportReady::class);

    // Nobody works in it any more; B is untouched.
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->a->cashier)->get('/dashboard')->assertForbidden();
    $this->actingAs($this->fixture->both->fresh())->get('/dashboard')->assertOk();
    expect(session(ResolveTenant::SESSION_KEY))->toBe($this->fixture->b->business->id);
    expect($this->fixture->b->business->fresh()->status)->toBe(Business::STATUS_ACTIVE);

    // Its owner is shown the closure, with the final export and a way to cancel.
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->a->owner)->get('/dashboard')->assertRedirect(route('businesses.index'));
    $this->get(route('businesses.index'))->assertOk()->assertSee('Cancel closure')->assertSee('Final export');
});

it('refuses a wrong code, and a code after too many wrong tries', function () {
    $code = closureCodeFor($this->a->owner);
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $try) {
        $this->actingAs($this->a->owner)->post(route('business.closure.confirm'), ['code' => $wrong])->assertSessionHasErrors('code');
    }
    $this->actingAs($this->a->owner)->post(route('business.closure.confirm'), ['code' => $code])->assertSessionHasErrors('code');

    expect($this->a->business->fresh()->status)->toBe(Business::STATUS_ACTIVE)
        ->and(BusinessClosure::count())->toBe(0);
});

it('lets an owner cancel during the grace period, and then nothing is erased', function () {
    $code = closureCodeFor($this->a->owner);
    $this->actingAs($this->a->owner)->post(route('business.closure.confirm'), ['code' => $code]);
    $closure = BusinessClosure::sole();

    // Not the other business's owner.
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->fixture->b->owner)->post(route('business.closure.cancel', $closure))->assertNotFound();

    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->a->owner)->post(route('business.closure.cancel', $closure))->assertRedirect(route('dashboard'));

    expect($this->a->business->fresh()->status)->toBe(Business::STATUS_ACTIVE)
        ->and($closure->fresh()->cancelled_by)->toBe($this->a->owner->id);

    $this->travel(31)->days();
    Artisan::call('businesses:erase-closed');
    expect(Business::find($this->a->business->id))->not->toBeNull();
});

it('erases the business when the grace period ends, and not before', function () {
    $code = closureCodeFor($this->a->owner);
    $this->actingAs($this->a->owner)->post(route('business.closure.confirm'), ['code' => $code]);
    $closure = BusinessClosure::sole();

    $this->travel(29)->days();
    Artisan::call('businesses:erase-closed');
    expect(Business::find($this->a->business->id))->not->toBeNull();

    $this->travel(2)->days();
    expect(Artisan::call('businesses:erase-closed'))->toBe(0);

    expect(Business::find($this->a->business->id))->toBeNull()
        ->and($closure->fresh()->erased_at)->not->toBeNull()
        ->and($closure->fresh()->summary['businesses'] ?? null)->toBe(1)
        ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles($this->a->business->uuid))->toBe([])
        ->and(Business::find($this->fixture->b->business->id))->not->toBeNull();
});
