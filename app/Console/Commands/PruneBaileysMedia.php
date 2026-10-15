<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Services\BaileysMediaService;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Expire stored WhatsApp media.
 *
 * Inbound media is written to storage/app/public/baileys/<session-uuid>/in/ and,
 * without this sweep, is kept forever — a single busy client phone will fill the
 * disk. Runs daily from routes/console.php.
 */
class PruneBaileysMedia extends Command
{
    use RunsForBusiness;

    protected $signature = 'baileys:prune-media
        {--days= : Delete media older than this many days (defaults to config baileys.media.retention_days)}
        {--shop= : Restrict to a single shop id}
        {--orphans : Also delete files on disk that no message row references}
        {--dry-run : Report what would be deleted without touching anything}';

    protected $description = 'Delete expired inbound Baileys (WhatsApp) media files and clear their message references';

    public function handleForBusiness(BaileysMediaService $media): int
    {
        $days = (int) ($this->option('days') ?? config('baileys.media.retention_days', 30));

        if ($days <= 0) {
            $this->error('Retention is disabled (days <= 0). Pass --days=N to prune anyway.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $shopId = $this->option('shop') ? (int) $this->option('shop') : null;
        $cutoff = now()->subDays($days);

        $this->info(sprintf(
            '%sPruning Baileys media older than %d days (before %s)%s.',
            $dryRun ? '[DRY RUN] ' : '',
            $days,
            $cutoff->toDateTimeString(),
            $shopId ? " for shop {$shopId}" : '',
        ));

        $deleted = 0;
        $bytes = 0;
        $touchedShops = [];

        BaileysMessage::query()
            ->whereNotNull('media_url')
            ->where('created_at', '<', $cutoff)
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->select(['id', 'shop_id', 'media_url', 'media_size'])
            ->chunkById(500, function ($messages) use ($dryRun, &$deleted, &$bytes, &$touchedShops): void {
                $ids = [];

                foreach ($messages as $message) {
                    // Private since stage 1c, public before.
                    [$diskName, $path] = BaileysMediaService::locate($message->media_url) ?? [null, null];
                    $disk = $diskName !== null ? Storage::disk($diskName) : null;

                    if ($disk !== null && $disk->exists($path)) {
                        $bytes += (int) ($message->media_size ?: $disk->size($path));

                        if (! $dryRun) {
                            $disk->delete($path);
                        }
                    }

                    $ids[] = $message->id;
                    $touchedShops[$message->shop_id] = true;
                    $deleted++;
                }

                if (! $dryRun && $ids !== []) {
                    // Keep the message (and its caption) — only the file goes.
                    BaileysMessage::whereIn('id', $ids)->update([
                        'media_url' => null,
                        'media_size' => null,
                    ]);
                }
            });

        if ($this->option('orphans')) {
            // This business's private folder, and the public one media lived
            // in before stage 1c (shared by every business).
            foreach ([
                [Storage::disk(TenantFiles::PRIVATE_DISK), app(TenantFiles::class)->path(BaileysMediaService::ROOT)],
                [Storage::disk(TenantFiles::PUBLIC_DISK), BaileysMediaService::ROOT],
            ] as [$disk, $root]) {
                [$orphanFiles, $orphanBytes] = $this->pruneOrphans($disk, $root, $dryRun);
                $deleted += $orphanFiles;
                $bytes += $orphanBytes;
            }
        }

        foreach (array_keys($touchedShops) as $touchedShopId) {
            $media->forgetShopUsage((int) $touchedShopId);
        }

        $this->info(sprintf(
            '%s%d file(s), %s reclaimed.',
            $dryRun ? '[DRY RUN] would delete ' : 'Deleted ',
            $deleted,
            $this->humanBytes($bytes),
        ));

        return self::SUCCESS;
    }

    /**
     * Delete files with no surviving message row — leftovers from deleted
     * sessions, failed writes, or media pruned before this command existed.
     *
     * @return array{0: int, 1: int}
     */
    protected function pruneOrphans(Filesystem $disk, string $root, bool $dryRun): array
    {
        // The media directory holds every business's sessions, and this runs
        // for one business (stage 1c): a directory is an orphan only when no
        // business has its session, and a session of another business is left
        // to that business's run.
        $everySession = DB::table('baileys_sessions')->pluck('uuid')->flip();
        $ownSessions = BaileysSession::query()->pluck('id', 'uuid');
        $files = 0;
        $bytes = 0;

        foreach ($disk->directories($root) as $directory) {
            $uuid = basename($directory);

            if ($everySession->has($uuid) && ! $ownSessions->has($uuid)) {
                continue;
            }

            // The whole session is gone — so is its media.
            if (! $everySession->has($uuid)) {
                foreach ($disk->allFiles($directory) as $file) {
                    $bytes += $disk->size($file);
                    $files++;
                }

                $this->line("  orphan session directory: {$directory}");

                if (! $dryRun) {
                    $disk->deleteDirectory($directory);
                }

                continue;
            }

            $referenced = BaileysMessage::query()
                ->where('baileys_session_id', $ownSessions[$uuid])
                ->whereNotNull('media_url')
                ->pluck('media_url')
                ->map(fn (string $url): ?string => BaileysMediaService::locate($url)[1] ?? null)
                ->filter()
                ->flip();

            foreach ($disk->allFiles($directory) as $file) {
                if ($referenced->has($file)) {
                    continue;
                }

                $bytes += $disk->size($file);
                $files++;

                if (! $dryRun) {
                    $disk->delete($file);
                }
            }
        }

        return [$files, $bytes];
    }

    protected function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return sprintf('%.1f %s', $value, $units[$index]);
    }
}
