<?php

use App\Models\User;
use App\Rules\RealEmail;
use App\Rules\StrongPassword;
use App\Rules\UniqueLogin;
use App\Support\Signup\EmailPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

function passes(object $rule, string $value, array $data = []): bool
{
    return Validator::make($data + ['field' => $value], ['field' => [$rule]])->passes();
}

beforeEach(function () {
    DB::table('disposable_email_domains')->insert(['domain' => 'mailinator.com', 'created_at' => now(), 'updated_at' => now()]);
    Cache::forget('signup.disposable-domains');
});

it('refuses placeholder and disposable addresses and accepts real ones', function (string $email, bool $ok) {
    expect(passes(new RealEmail, $email))->toBe($ok);
})->with([
    'a test mailbox on gmail' => ['test@gmail.com', false],
    'tagged test mailbox' => ['test+1@gmail.com', false],
    'numbered test mailbox' => ['test42@gmail.com', false],
    'example domain' => ['demo@example.com', false],
    'test domain' => ['anything@test.com', false],
    'reserved suffix' => ['owner@shop.localhost', false],
    'disposable provider' => ['someone@mailinator.com', false],
    'not an address' => ['not-an-email', false],
    'a plus tag on a real mailbox' => ['john+shop@gmail.com', true],
    'a role-style address' => ['info@realshop.co.ke', true],
    'sales address' => ['sales@realshop.co.ke', true],
]);

it('says the same thing whichever test an address fails', function () {
    $messages = collect(['test@gmail.com', 'a@example.com', 'x@mailinator.com'])->map(
        fn ($email) => Validator::make(['field' => $email], ['field' => [new RealEmail]])->errors()->first('field'),
    )->unique();

    expect($messages->all())->toBe([__(EmailPolicy::GENERIC_MESSAGE)]);
});

it('normalises tags and gmail dots', function () {
    expect(EmailPolicy::normalise('J.Oh.N+shop@Gmail.com'))->toBe('john@gmail.com')
        ->and(EmailPolicy::normalise('john@googlemail.com'))->toBe('john@gmail.com')
        ->and(EmailPolicy::normalise('Info+x@Shop.co.ke'))->toBe('info@shop.co.ke');
});

it('lets an address through when DNS cannot answer, and refuses a domain with no mail', function () {
    config(['security.signup.check_dns' => true]);
    Cache::flush();

    // checkdnsrr is not fakeable; the cache is the seam the policy reads first.
    Cache::put('signup.dns.no-mail.invalid-shop.co.ke', false, 60);
    Cache::put('signup.dns.has-mail.shop.co.ke', true, 60);

    expect(passes(new RealEmail, 'a@no-mail.invalid-shop.co.ke'))->toBeFalse()
        ->and(passes(new RealEmail, 'a@has-mail.shop.co.ke'))->toBeTrue();
});

it('refuses the same login respelled', function () {
    User::factory()->create(['email' => 'john@gmail.com']);

    expect(passes(new UniqueLogin, 'john@gmail.com'))->toBeFalse()
        ->and(passes(new UniqueLogin, 'John+shop@gmail.com'))->toBeFalse()
        ->and(passes(new UniqueLogin, 'j.o.h.n@gmail.com'))->toBeFalse()
        ->and(passes(new UniqueLogin, 'jane@gmail.com'))->toBeTrue();
});

it('keeps each login\'s normalised email up to date', function () {
    $user = User::factory()->create(['email' => 'A.B+x@gmail.com']);
    expect($user->fresh()->email_normalized)->toBe('ab@gmail.com');

    $user->update(['email' => 'new@shop.co.ke']);
    expect($user->fresh()->email_normalized)->toBe('new@shop.co.ke');
});

it('refuses weak passwords and accepts a passphrase', function (string $password, bool $ok) {
    expect(passes(new StrongPassword, $password))->toBe($ok);
})->with([
    'too short' => ['Zq7!kd2', false],
    'too long' => [str_repeat('aB3$', 40), false],
    'a common one with digits' => ['Password123', false],
    'a word and a year' => ['Welcome2024', false],
    'keyboard walk with digits' => ['qwerty123456', false],
    'a symbol-padded common word' => ['Admin@12345', false],
    'a number run' => ['1234567890', false],
    'one repeated character' => ['aaaaaaaaaaaa', false],
    'a long passphrase' => ['purple tractors drink warm milk on tuesdays', true],
    'a random mix' => ['k7#Vq2!mR9xLw', true],
]);

it('refuses a password built from the person\'s own details', function () {
    expect(passes(new StrongPassword, 'wanjiku-mama-mboga-1', ['name' => 'Wanjiku Kamau', 'business_name' => 'Mama Mboga Stores']))->toBeFalse()
        ->and(passes(new StrongPassword, 'xx-mamamboga-xx-91', ['business_name' => 'Mama Mboga Stores']))->toBeFalse()
        ->and(passes(new StrongPassword, 'owner2024shop!', ['email' => 'owner@shop.co.ke']))->toBeFalse()
        ->and(passes(new StrongPassword, 'blue-river-stones-77', ['name' => 'Wanjiku Kamau']))->toBeTrue();
});

it('refuses a breached password and falls back to the local list when the service fails', function () {
    config(['security.signup.breach_check' => true]);
    $password = 'blue-river-stones-77';
    $hash = strtoupper(sha1($password));

    $mode = 'breached';
    Http::fake(['api.pwnedpasswords.com/*' => function () use (&$mode, $hash) {
        return match ($mode) {
            'breached' => Http::response(substr($hash, 5).':42
0000000000000000000000000000000000A:1', 200),
            'down' => Http::response('', 503),
            default => throw new RuntimeException('timeout'),
        };
    }]);

    expect(passes(new StrongPassword, $password))->toBeFalse();

    $mode = 'down';
    expect(passes(new StrongPassword, $password))->toBeTrue()
        ->and(passes(new StrongPassword, 'Password123'))->toBeFalse();

    $mode = 'timeout';
    expect(passes(new StrongPassword, $password))->toBeTrue();
});

it('loads the disposable list from a file without a deploy', function () {
    $file = tempnam(sys_get_temp_dir(), 'dom');
    file_put_contents($file, "# comment\nNew-Trash.example.io\nmailinator.com\n\nnodot\n");

    $this->artisan('signup:load-disposable-domains', ['file' => $file])->assertSuccessful();

    expect(DB::table('disposable_email_domains')->where('domain', 'new-trash.example.io')->exists())->toBeTrue()
        ->and(DB::table('disposable_email_domains')->where('domain', 'nodot')->exists())->toBeFalse()
        ->and(DB::table('disposable_email_domains')->where('domain', 'mailinator.com')->count())->toBe(1)
        ->and(passes(new RealEmail, 'a@new-trash.example.io'))->toBeFalse();

    unlink($file);
});
