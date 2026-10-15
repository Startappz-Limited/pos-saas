<?php

use App\Actions\Product\CreateProductAction;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Product;
use App\Models\User;
use App\Services\UserService;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\TwoBusinesses;

/**
 * Business files (stage 1c): uploads land under the business's folder;
 * private files (profile photos, WhatsApp media) aren't publicly reachable
 * and are served only to the business's members; each business has a quota;
 * files from before are moved by files:move-to-business.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('tenant');
    $this->fixture = TwoBusinesses::create();
    app(TenantContext::class)->set($this->fixture->a->business);
    $this->prefix = $this->fixture->a->business->uuid.'/';
});

function signInMember(User $user): void
{
    test()->actingAs($user);
    app(TenantContext::class)->forget();
}

it('stores product images under the business\'s folder, publicly', function () {
    $product = app(CreateProductAction::class)->execute([
        ...Product::factory()->raw(['shop_id' => $this->fixture->a->shops[0]->id]),
        'image' => UploadedFile::fake()->image('p.jpg'),
    ]);

    expect($product->image)->toStartWith($this->prefix.'products/');
    Storage::disk('public')->assertExists($product->image);
});

it('keeps profile photos private, under the business\'s folder, served to its members only', function () {
    $owner = $this->fixture->a->owner;
    $user = app(UserService::class)->update($owner, ['profile_photo' => UploadedFile::fake()->image('me.jpg')]);

    expect($user->profile_photo)->toStartWith($this->prefix.'profile-photos/')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($user->profile_photo_url)->toBe(route('tenant-files.show', ['path' => $user->profile_photo]));
    Storage::disk('tenant')->assertExists($user->profile_photo);

    signInMember($this->fixture->a->cashier);
    $this->get($user->profile_photo_url)->assertOk();

    signInMember($this->fixture->b->owner);
    $this->get($user->profile_photo_url)->assertNotFound();

    auth()->logout();
    $this->get($user->profile_photo_url)->assertRedirect(route('login'));
});

it('refuses paths outside the business\'s folder', function () {
    Storage::disk('tenant')->put($this->fixture->b->business->uuid.'/profile-photos/b.jpg', 'b');
    signInMember($this->fixture->a->owner);

    $this->get(route('tenant-files.show', ['path' => $this->prefix.'../'.$this->fixture->b->business->uuid.'/profile-photos/b.jpg']))->assertNotFound();
    $this->get(route('tenant-files.show', ['path' => $this->fixture->b->business->uuid.'/profile-photos/b.jpg']))->assertNotFound();
});

it('serves WhatsApp media only for sessions in shops the viewer may access', function () {
    $session = BaileysSession::factory()->create(['shop_id' => $this->fixture->a->shops[1]->id]);
    $path = $this->prefix.'baileys/'.$session->uuid.'/in/x.jpg';
    Storage::disk('tenant')->put($path, 'x');

    signInMember($this->fixture->a->owner);
    $this->get(route('tenant-files.show', ['path' => $path]))->assertOk();

    // The cashier works only in the main shop; the session is the branch's.
    signInMember($this->fixture->a->cashier);
    $this->get(route('tenant-files.show', ['path' => $path]))->assertNotFound();
});

it('refuses an upload that would take the business over its quota', function () {
    $this->fixture->a->business->update(['settings' => ['files_quota_bytes' => 1024]]);
    app(TenantContext::class)->set($this->fixture->a->business->fresh());

    expect(fn () => app(TenantFiles::class)->storePrivate(UploadedFile::fake()->create('big.pdf', 10), 'docs'))
        ->toThrow(ValidationException::class)
        ->and(app(TenantFiles::class)->putIfRoom('docs/small.txt', 'tiny'))->toBe($this->prefix.'docs/small.txt');
});

it('moves a business\'s files from before into its folder, dry run first, originals kept until asked', function () {
    $a = $this->fixture->a;
    $session = BaileysSession::factory()->create(['shop_id' => $a->shops[0]->id]);
    $message = BaileysMessage::factory()->create([
        'baileys_session_id' => $session->id, 'shop_id' => $a->shops[0]->id,
        'media_url' => asset("storage/baileys/{$session->uuid}/in/m.jpg"),
    ]);
    Storage::disk('public')->put("baileys/{$session->uuid}/in/m.jpg", 'media');
    Storage::disk('public')->put('products/old.jpg', 'image');
    Storage::disk('public')->put('profile-photos/me.jpg', 'photo');
    Product::whereKey($a->product->id)->update(['image' => 'products/old.jpg']);
    User::whereKey($a->owner->id)->update(['profile_photo' => 'profile-photos/me.jpg']);
    // Business B's file stays where it is.
    Storage::disk('public')->put('products/b.jpg', 'b');
    app(TenantContext::class)->run($this->fixture->b->business, fn () => Product::whereKey($this->fixture->b->product->id)->update(['image' => 'products/b.jpg']));
    app(TenantContext::class)->forget();

    $this->artisan('files:move-to-business', ['--business' => $a->business->id])->assertSuccessful();
    expect($a->product->fresh()->image)->toBe('products/old.jpg');

    $this->artisan('files:move-to-business', ['--business' => $a->business->id, '--apply' => true])->assertSuccessful();
    app(TenantContext::class)->set($a->business);

    expect($a->product->fresh()->image)->toBe($this->prefix.'products/old.jpg')
        ->and($a->owner->fresh()->profile_photo)->toBe($this->prefix.'profile-photos/me.jpg')
        ->and($message->fresh()->media_url)->toBe(route('tenant-files.show', ['path' => $this->prefix."baileys/{$session->uuid}/in/m.jpg"]));
    Storage::disk('public')->assertExists($this->prefix.'products/old.jpg');
    Storage::disk('tenant')->assertExists($this->prefix.'profile-photos/me.jpg');
    Storage::disk('tenant')->assertExists($this->prefix."baileys/{$session->uuid}/in/m.jpg");
    Storage::disk('public')->assertExists('profile-photos/me.jpg');

    app(TenantContext::class)->forget();
    $this->artisan('files:move-to-business', ['--business' => $a->business->id, '--apply' => true, '--delete-originals' => true])->assertSuccessful();
    Storage::disk('public')->assertMissing('products/old.jpg');
    Storage::disk('public')->assertExists('products/b.jpg');
    expect(app(TenantContext::class)->run($this->fixture->b->business, fn () => $this->fixture->b->product->fresh()->image))->toBe('products/b.jpg');
});
