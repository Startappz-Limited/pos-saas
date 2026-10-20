<?php

use App\Services\InvoiceNumberService;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TwoBusinesses;

/**
 * Tenant-owned settings (stage 3): name, logo, receipt text, currency,
 * timezone and invoice prefix replace the platform's on receipts, PDFs and
 * amounts (decision 4.9: currency is per business).
 */
beforeEach(function () {
    Storage::fake(TenantFiles::PUBLIC_DISK);
    Storage::fake(TenantFiles::PRIVATE_DISK);
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->tenant = app(TenantContext::class);
});

function settingsForm(array $overrides = []): array
{
    return [
        'name' => 'Acme Hardware', 'currency' => 'UGX', 'timezone' => 'Africa/Kampala',
        'receipt_header' => "PO Box 1\nKampala", 'receipt_footer' => 'No refunds after 7 days', 'invoice_prefix' => 'acme',
        ...$overrides,
    ];
}

it('saves the owner\'s settings for their business only', function () {
    $this->actingAs($this->a->owner)->put(route('business.settings.update'), settingsForm())
        ->assertRedirect(route('business.settings.edit'))->assertSessionHas('success');

    $a = $this->a->business->fresh();
    expect($a->name)->toBe('Acme Hardware')
        ->and($a->currency)->toBe('UGX')
        ->and($a->timezone)->toBe('Africa/Kampala')
        ->and($a->invoice_prefix)->toBe('ACME')
        ->and($this->b->business->fresh()->name)->not->toBe('Acme Hardware')
        ->and($this->b->business->fresh()->currency)->not->toBe('UGX');
});

it('refuses an unknown currency, a bad prefix and a non-image logo', function () {
    $this->actingAs($this->a->owner)->put(route('business.settings.update'), settingsForm([
        'currency' => 'XXX', 'invoice_prefix' => 'a b!', 'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    ]))->assertSessionHasErrors(['currency', 'invoice_prefix', 'logo']);
});

it('refuses someone without the settings permission', function () {
    $this->actingAs($this->fixture->a->cashier)->put(route('business.settings.update'), settingsForm())->assertForbidden();
    $this->actingAs($this->fixture->a->cashier)->get(route('business.settings.edit'))->assertForbidden();
});

it('stores the logo in the business\'s folder and replaces it', function () {
    $this->actingAs($this->a->owner)->put(route('business.settings.update'), settingsForm(['logo' => UploadedFile::fake()->image('one.png')]));
    $first = $this->a->business->fresh()->logo_path;

    expect($first)->toStartWith($this->a->business->uuid.'/')
        ->and(Storage::disk(TenantFiles::PUBLIC_DISK)->exists($first))->toBeTrue();

    $this->actingAs($this->a->owner)->put(route('business.settings.update'), settingsForm(['logo' => UploadedFile::fake()->image('two.png')]));
    $second = $this->a->business->fresh()->logo_path;

    expect($second)->not->toBe($first)
        ->and(Storage::disk(TenantFiles::PUBLIC_DISK)->exists($first))->toBeFalse();

    $this->actingAs($this->a->owner)->put(route('business.settings.update'), settingsForm(['remove_logo' => '1']));
    expect($this->a->business->fresh()->logo_path)->toBeNull()
        ->and(Storage::disk(TenantFiles::PUBLIC_DISK)->exists($second))->toBeFalse();
});

it('formats amounts in the active business\'s currency', function () {
    $this->a->business->update(['currency' => 'UGX']);

    expect($this->tenant->run($this->a->business->fresh(), fn () => [currency_code(), currency_symbol(), format_currency(1234.5)]))
        ->toBe(['UGX', 'USh', 'USh1,234.50'])
        ->and($this->tenant->run($this->b->business->fresh(), fn () => currency_code()))->toBe('KES');
});

it('puts the business\'s branding and the shop\'s Till on the receipt', function () {
    $this->a->business->update(['name' => 'Acme Hardware', 'receipt_header' => 'PO Box 1', 'receipt_footer' => 'No refunds after 7 days']);
    $this->a->shops[1]->forceFill(['mpesa_shortcode' => '888999', 'mpesa_shortcode_type' => 'till', 'mpesa_verified_at' => now()])->save();

    $this->actingAs($this->a->owner)->get(route('sales.receipt', $this->a->sale))
        ->assertOk()->assertSee('PO Box 1')->assertSee('No refunds after 7 days')->assertSee('Till: 888999')
        ->assertDontSee('Please keep this receipt for your records');
});

it('numbers new invoices with the business\'s prefix', function () {
    $this->a->business->update(['invoice_prefix' => 'ACME']);

    $number = $this->tenant->run($this->a->business->fresh(), fn () => app(InvoiceNumberService::class)->next($this->a->shops[0]));

    expect($number)->toStartWith('ACME-');
});
