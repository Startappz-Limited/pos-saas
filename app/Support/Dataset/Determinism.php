<?php

namespace App\Support\Dataset;

use Faker\Generator as Faker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Makes a dataset build reproducible: the same seed produces the same rows,
 * byte for byte, on every run (stage 0, live-like dataset).
 *
 * Every source of randomness on the seeding paths is pinned: Faker (both
 * instances Laravel keeps), mt_rand, Str::uuid() and Str::random(), and the
 * clock. Everything that would leave the machine or depend on time or the
 * network (mail, notifications, queued jobs, HTTP) is replaced by Laravel's
 * fakes, so a build never messages anyone.
 */
class Determinism
{
    /**
     * A bcrypt hash of "password", fixed so users get identical rows on every
     * build (Hash::make() salts randomly).
     */
    public const PASSWORD_HASH = '$2y$04$EaMf9wjURtStnU5UWrYXqONwQ.L2ER4NgKZbtVVPNf1vF3HrVb0Fa';

    public static function start(int $seed, string $now): void
    {
        mt_srand($seed);
        fake()->seed($seed);
        app(Faker::class)->seed($seed);

        // unique() remembers every value it has handed out in this process,
        // so a second build in the same process would get different values.
        fake()->unique(true);
        app(Faker::class)->unique(true);

        Str::createUuidsUsing(fn () => Uuid::fromString(vsprintf('%08x-%04x-4%03x-%04x-%012x', [
            mt_rand(0, 0xFFFFFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFF),
            mt_rand(0, 0x3FFF) | 0x8000,
            (mt_rand(0, 0xFFFFFF) << 24) | mt_rand(0, 0xFFFFFF),
        ])));

        Str::createRandomStringsUsing(function (int $length): string {
            $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $string = '';

            for ($i = 0; $i < $length; $i++) {
                $string .= $alphabet[mt_rand(0, 61)];
            }

            return $string;
        });

        Carbon::setTestNow(Carbon::parse($now));

        Bus::fake();
        Mail::fake();
        Notification::fake();
        Http::fake();
    }

    public static function travelTo(Carbon|string $moment): void
    {
        Carbon::setTestNow($moment instanceof Carbon ? $moment : Carbon::parse($moment));
    }

    public static function stop(): void
    {
        Str::createUuidsNormally();
        Str::createRandomStringsNormally();
        Carbon::setTestNow();
    }
}
