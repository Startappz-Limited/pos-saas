<?php

namespace App\Support\Signup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * What counts as a real address at signup (Phase 6): the normal form used to
 * spot respelled duplicates, the blocked placeholder names and domains, and
 * whether the domain can receive mail. Role-style addresses (info@, sales@)
 * on a real domain are allowed.
 */
class EmailPolicy
{
    public const GENERIC_MESSAGE = 'Please use a real email address you have access to.';

    private const BLOCKED_LOCAL_PARTS = [
        'test', 'testing', 'tester', 'demo', 'example', 'sample', 'user', 'asdf', 'qwerty',
        'abc', 'abc123', 'aaa', 'xxx', 'fake', 'noreply', 'no-reply', 'null', 'foo', 'bar', 'foobar',
    ];

    private const BLOCKED_DOMAINS = [
        'example.com', 'example.org', 'example.net', 'test.com', 'test.org', 'test.net',
        'domain.com', 'email.com',
    ];

    private const BLOCKED_SUFFIXES = ['.test', '.example', '.invalid', '.localhost'];

    private const GMAIL_DOMAINS = ['gmail.com', 'googlemail.com'];

    /**
     * Lowercase, without a +tag and, for Gmail, without dots.
     */
    public static function normalise(string $email): string
    {
        $email = Str::lower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $local = explode('+', $local, 2)[0];

        if (in_array($domain, self::GMAIL_DOMAINS, true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    public function isPlaceholder(string $email): bool
    {
        [$local, $domain] = array_pad(explode('@', self::normalise($email), 2), 2, '');

        if (in_array($local, self::BLOCKED_LOCAL_PARTS, true) || preg_match('/^test\d{1,3}$/', $local) === 1) {
            return true;
        }

        if (in_array($domain, self::BLOCKED_DOMAINS, true) || Str::endsWith($domain, self::BLOCKED_SUFFIXES)) {
            return true;
        }

        return $this->isDisposable($domain);
    }

    public function isDisposable(string $domain): bool
    {
        $domains = Cache::remember('signup.disposable-domains', 3600, function (): array {
            return Schema::hasTable('disposable_email_domains')
                ? DB::table('disposable_email_domains')->pluck('domain')->all()
                : [];
        });

        return in_array(Str::lower($domain), $domains, true);
    }

    /**
     * Whether the domain accepts mail (an MX or A record), cached. A DNS
     * failure lets the address through and is logged.
     */
    public function domainAcceptsMail(string $domain): bool
    {
        if (! config('security.signup.check_dns')) {
            return true;
        }

        return Cache::remember('signup.dns.'.Str::lower($domain), 3600, function () use ($domain): bool {
            try {
                return checkdnsrr($domain.'.', 'MX') || checkdnsrr($domain.'.', 'A');
            } catch (\Throwable $e) {
                Log::warning('Signup DNS check failed; allowing the address.', ['domain' => $domain, 'error' => $e->getMessage()]);

                return true;
            }
        });
    }
}
