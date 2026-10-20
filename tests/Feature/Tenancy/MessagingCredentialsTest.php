<?php

use App\Http\Startappz\Services\SMSservice;
use App\Models\BusinessIntegration;
use App\Notifications\Channels\SmsChannel;
use App\Services\WhatsAppService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\TwoBusinesses;

/**
 * SMS and WhatsApp Cloud send through the business's own encrypted
 * credentials (stage 3), never the platform's or another business's.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->tenant = app(TenantContext::class);
    config(['services.sms.url' => 'https://platform-sms.test/api', 'services.sms.token' => 'platform-token']);
    config(['services.whatsapp.api_url' => 'https://platform-wa.test/messages', 'services.whatsapp.api_token' => 'platform-token']);
});

it('saves a business\'s SMS account encrypted, never shows it again, and keeps blanks', function () {
    $this->actingAs($this->a->owner)->put(route('business.integrations.update', 'sms'), [
        'api_url' => 'https://sms.acme.test/send', 'api_token' => 'acme-secret-token', 'sender_id' => 'ACME',
    ])->assertRedirect(route('business.integrations.edit'));

    expect(DB::table('business_integrations')->value('credentials'))->not->toContain('acme-secret-token');

    $this->actingAs($this->a->owner)->get(route('business.integrations.edit'))->assertOk()->assertDontSee('acme-secret-token');

    $this->actingAs($this->a->owner)->put(route('business.integrations.update', 'sms'), ['api_url' => 'https://sms.acme.test/v2', 'api_token' => '', 'sender_id' => ''])
        ->assertSessionHasNoErrors();

    $credentials = $this->tenant->run($this->a->business, fn () => BusinessIntegration::forShop('sms')->credentials);
    expect($credentials)->toMatchArray(['api_url' => 'https://sms.acme.test/v2', 'api_token' => 'acme-secret-token', 'sender_id' => 'ACME']);
});

it('refuses plain http URLs, unknown types and people without the settings permission', function () {
    $this->actingAs($this->a->owner)->put(route('business.integrations.update', 'sms'), ['api_url' => 'http://insecure.test', 'api_token' => 'x'])
        ->assertSessionHasErrors('api_url');
    $this->actingAs($this->a->owner)->put(route('business.integrations.update', 'telegram'), [])->assertNotFound();
    $this->actingAs($this->a->cashier)->put(route('business.integrations.update', 'sms'), ['api_url' => 'https://x.test', 'api_token' => 'x'])->assertForbidden();
});

it('sends SMS through the business\'s own account, and another business through its own', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);

    $this->tenant->run($this->a->business, function (): void {
        useSmsAccount('https://sms.acme.test/send', 'acme-token', 'ACME');
        app(SMSservice::class)->sendSms('254712345678', 'Hello from A');
    });
    $this->tenant->run($this->b->business, function (): void {
        useSmsAccount('https://sms.bravo.test/send', 'bravo-token', 'BRAVO');
        app(SMSservice::class)->sendSms('254712345678', 'Hello from B');
    });

    Http::assertSent(fn ($request) => $request->url() === 'https://sms.acme.test/send'
        && $request->hasHeader('Authorization', 'Bearer acme-token') && $request['senderid'] === 'ACME');
    Http::assertSent(fn ($request) => $request->url() === 'https://sms.bravo.test/send'
        && $request->hasHeader('Authorization', 'Bearer bravo-token') && $request['senderid'] === 'BRAVO');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'platform-sms'));
});

it('does not fall back to the platform\'s account when the business has none', function () {
    Http::fake();

    $result = $this->tenant->run($this->a->business, fn () => app(SMSservice::class)->sendSms('254712345678', 'Hi'));

    expect($result['success'])->toBeFalse()
        ->and($this->tenant->run($this->a->business, fn () => SmsChannel::isConfigured()))->toBeFalse()
        ->and($this->tenant->run($this->a->business, fn () => WhatsAppService::isConfigured()))->toBeFalse();
    Http::assertNothingSent();
});

it('sends WhatsApp Cloud messages through the business\'s own account', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

    $this->tenant->run($this->a->business, function (): void {
        useWhatsAppAccount('https://graph.facebook.com/v22.0/111/messages', 'acme-wa-token');
        expect(app(WhatsAppService::class)->sendTextMessage('254712345678', 'Hi')['success'])->toBeTrue();
    });

    Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v22.0/111/messages'
        && $request->hasHeader('Authorization', 'Bearer acme-wa-token'));
});

it('uses the platform\'s account only when acting for no business', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $this->tenant->forget();

    app(SMSservice::class)->sendSms('254712345678', 'Platform message');

    Http::assertSent(fn ($request) => $request->url() === 'https://platform-sms.test/api');
});
