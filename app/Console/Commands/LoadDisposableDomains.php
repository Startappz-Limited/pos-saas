<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Loads the disposable-email provider list into `disposable_email_domains`
 * (Phase 6). The list is data: run this again with a newer file (for example
 * the `disposable-email-domains` project's list) to update it, without a
 * deploy. Existing rows are kept; the command only adds.
 */
class LoadDisposableDomains extends Command
{
    protected $signature = 'signup:load-disposable-domains {file? : A text file with one domain per line (default: the list shipped in database/data)}';

    protected $description = 'Add disposable-email provider domains to the signup blocklist';

    public function handle(): int
    {
        $file = $this->argument('file') ?? database_path('data/disposable-domains.txt');

        if (! is_readable($file)) {
            $this->error("Cannot read {$file}.");

            return self::FAILURE;
        }

        $domains = collect(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            ->map(fn (string $line) => mb_strtolower(trim($line)))
            ->filter(fn (string $line) => $line !== '' && ! str_starts_with($line, '#') && str_contains($line, '.'))
            ->unique()
            ->values();

        $before = DB::table('disposable_email_domains')->count();

        foreach ($domains->chunk(500) as $chunk) {
            DB::table('disposable_email_domains')->insertOrIgnore(
                $chunk->map(fn (string $domain) => ['domain' => $domain, 'created_at' => now(), 'updated_at' => now()])->all(),
            );
        }

        Cache::forget('signup.disposable-domains');

        $this->info('Added '.(DB::table('disposable_email_domains')->count() - $before).' of '.$domains->count().' domains.');

        return self::SUCCESS;
    }
}
