<?php

use App\Actions\Shop\UpdateShopAction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('updates shop without changing integration config', function () {
    $shop = Shop::factory()->create(['name' => 'Old Name']);
    $action = app(UpdateShopAction::class);

    $data = ['name' => 'New Name'];

    $updatedShop = $action->execute($shop, $data);

    expect($updatedShop->name)->toBe('New Name');
});

test('updates shop with new woocommerce integration', function () {
    $shop = Shop::factory()->create();
    $action = app(UpdateShopAction::class);

    $data = [
        'name' => $shop->name,
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
                'consumer_key' => 'ck_new',
                'consumer_secret' => 'cs_new',
            ],
        ],
    ];

    $updatedShop = $action->execute($shop, $data);

    expect($updatedShop->settings['integrations'])->toHaveKey('woocommerce')
        ->and($updatedShop->settings['integrations']['woocommerce']['enabled'])->toBeTrue();

    // Verify encryption
    $encryptedKey = $updatedShop->settings['integrations']['woocommerce']['consumer_key'];
    expect(Crypt::decrypt($encryptedKey))->toBe('ck_new');
});

test('updates existing integration config', function () {
    $shop = Shop::factory()->create([
        'settings' => [
            'integrations' => [
                'woocommerce' => [
                    'enabled' => true,
                    'store_url' => 'https://old.com',
                    'consumer_key' => Crypt::encrypt('ck_old'),
                    'consumer_secret' => Crypt::encrypt('cs_old'),
                ],
            ],
        ],
    ]);

    $action = app(UpdateShopAction::class);

    $data = [
        'name' => $shop->name,
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://new.com',
                'consumer_key' => 'ck_new',
                'consumer_secret' => 'cs_new',
            ],
        ],
    ];

    $updatedShop = $action->execute($shop, $data);

    expect($updatedShop->settings['integrations']['woocommerce']['store_url'])->toBe('https://new.com');

    $decryptedKey = Crypt::decrypt($updatedShop->settings['integrations']['woocommerce']['consumer_key']);
    expect($decryptedKey)->toBe('ck_new');
});

test('preserves existing integrations when updating another', function () {
    $shop = Shop::factory()->create([
        'settings' => [
            'integrations' => [
                'woocommerce' => [
                    'enabled' => true,
                    'store_url' => 'https://test.com',
                ],
            ],
        ],
    ]);

    $action = app(UpdateShopAction::class);

    $data = [
        'name' => $shop->name,
        'integrations' => [
            'shopify' => [
                'enabled' => true,
                'shop_domain' => 'test.myshopify.com',
                'access_token' => 'shpat_test',
            ],
        ],
    ];

    $updatedShop = $action->execute($shop, $data);

    expect($updatedShop->settings['integrations'])->toHaveKey('woocommerce')
        ->and($updatedShop->settings['integrations'])->toHaveKey('shopify');
});

test('removes integration when disabled and empty', function () {
    $shop = Shop::factory()->create([
        'settings' => [
            'integrations' => [
                'woocommerce' => [
                    'enabled' => true,
                    'store_url' => 'https://test.com',
                ],
            ],
        ],
    ]);

    $action = app(UpdateShopAction::class);

    $data = [
        'name' => $shop->name,
        'integrations' => [
            'woocommerce' => [
                'enabled' => false,
            ],
        ],
    ];

    $updatedShop = $action->execute($shop, $data);

    expect($updatedShop->settings['integrations'] ?? [])->not->toHaveKey('woocommerce');
});

test('a partial settings payload does not wipe the other settings sections', function () {
    // The shop settings blob holds several independent sections and each form
    // posts only its own. Replacing the column wholesale would silently drop the
    // sale-notification switches the moment someone saved the tax panel.
    $shop = Shop::factory()->create([
        'settings' => [
            'sale_notifications' => ['all' => true, 'invoice_pdf' => false],
            'integrations' => ['shopify' => ['enabled' => true, 'shop_domain' => 'x.myshopify.com']],
        ],
    ]);

    $updated = app(UpdateShopAction::class)->execute($shop, [
        'name' => $shop->name,
        'settings' => ['tax' => ['prices_include_tax' => false]],
    ]);

    expect($updated->settings['tax']['prices_include_tax'])->toBeFalse()
        ->and($updated->settings['sale_notifications'])->toBe(['all' => true, 'invoice_pdf' => false])
        ->and($updated->settings['integrations'])->toHaveKey('shopify');
});
