<?php

use App\Enums\SocialPlatform;
use App\Models\Shop;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * social_accounts.credentials holds an encrypted string, not JSON. As a JSON
 * column it was rejected by MySQL and MariaDB (only SQLite accepted it), so
 * the column is TEXT (stage 0, step 4b; part of the baseline since stage 1a).
 */
function connectedAccount(array $credentials): SocialAccount
{
    $account = new SocialAccount([
        'shop_id' => Shop::factory()->create()->id,
        'platform' => SocialPlatform::FACEBOOK,
        'account_name' => 'Demo page',
        'external_account_id' => 'page_'.fake()->unique()->numberBetween(1, 999999),
    ]);
    $account->setCredentials($credentials);
    $account->save();

    return $account;
}

it('stores encrypted credentials and reads them back from the database', function () {
    $account = connectedAccount(['access_token' => 'secret-token', 'page_id' => '42']);

    $stored = DB::table('social_accounts')->where('id', $account->id)->value('credentials');

    expect($stored)->not->toContain('secret-token')
        ->and(json_validate($stored))->toBeFalse()
        ->and($account->fresh()->getCredentials())->toBe(['access_token' => 'secret-token', 'page_id' => '42']);
});

it('keeps accounts without credentials', function () {
    $account = connectedAccount([]);
    $account->setCredentials(null);
    $account->save();

    expect($account->fresh()->getCredentials())->toBe([]);
});

it('stores credentials in a text column, not JSON', function () {
    expect(Schema::getColumnType('social_accounts', 'credentials'))->toBe('text');
})->skip(fn () => DB::getDriverName() === 'sqlite', 'SQLite reports column types loosely; checked on MySQL.');
