<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\BaileysMediaService;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Moves a business's files from before stage 1c into its folder: product
 * images to {business uuid}/... on the public disk, profile photos and
 * WhatsApp media to {business uuid}/... on the private disk. Dry run by
 * default.
 *
 * Each file is copied, the copy checked, and only then is the record pointed
 * at it. Originals are kept unless --delete-originals is given, which removes
 * an original only once its copy is verified. Run it once per business; a
 * file already in the business's folder is left alone.
 */
class MoveFilesToBusiness extends Command
{
    use RunsForBusiness;

    protected $signature = 'files:move-to-business
        {--apply : Copy the files and update the records (without it, only report)}
        {--delete-originals : With --apply, delete each original once its copy is verified}';

    protected $description = 'Move a business\'s product images, profile photos and WhatsApp media into its own folder (dry run unless --apply).';

    /** @var array<string, array{0: int, 1: int, 2: int}> moved, missing and already moved, per kind */
    private array $counts = [];

    public function handleForBusiness(TenantFiles $files): int
    {
        $apply = (bool) $this->option('apply');
        $prefix = $files->prefix().'/';

        // Product and variation images: public, under the business's folder.
        foreach ([Product::class, ProductVariation::class] as $model) {
            // Soft-deleted records keep their images too.
            $query = $model::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $query->withTrashed();
            }

            $query->whereNotNull('image')
                ->select(['id', 'image'])->chunkById(200, function ($rows) use ($model, $prefix, $apply): void {
                    foreach ($rows as $row) {
                        $target = $this->relocate('product images', $prefix, $row->image, TenantFiles::PUBLIC_DISK, $apply);
                        if ($target !== null && $apply) {
                            $model::query()->whereKey($row->id)->update(['image' => $target]);
                        }
                    }
                });
        }

        // Profile photos of this business's members: private.
        // A photo already moved into another business's folder (a member of
        // several) is left there.
        User::query()->inCurrentBusiness()->whereNotNull('profile_photo')
            ->where(fn ($query) => $query->where('profile_photo', 'not like', '%/profile-photos/%')->orWhere('profile_photo', 'like', $prefix.'%'))
            ->select(['id', 'profile_photo'])->chunkById(200, function ($users) use ($prefix, $apply): void {
                foreach ($users as $user) {
                    $target = $this->relocate('profile photos', $prefix, $user->profile_photo, TenantFiles::PRIVATE_DISK, $apply);
                    if ($target !== null && $apply) {
                        User::query()->whereKey($user->id)->update(['profile_photo' => $target]);
                    }
                }
            });

        // WhatsApp media of this business's sessions: private.
        $sessions = BaileysSession::query()->pluck('id');
        BaileysMessage::query()->whereIn('baileys_session_id', $sessions)->whereNotNull('media_url')
            ->select(['id', 'media_url'])->chunkById(200, function ($messages) use ($files, $prefix, $apply): void {
                foreach ($messages as $message) {
                    [, $path] = BaileysMediaService::locate($message->media_url) ?? [null, null];
                    if ($path === null) {
                        continue;
                    }

                    $target = $this->relocate('WhatsApp media', $prefix, $path, TenantFiles::PRIVATE_DISK, $apply);
                    if ($target !== null && $apply) {
                        BaileysMessage::query()->whereKey($message->id)->update(['media_url' => $files->url($target)]);
                    }
                }
            });

        $files->forgetUsage();

        $this->table(['Files', $apply ? 'Moved' : 'To move', 'Missing on disk (left as they are)', 'Already moved'], collect($this->counts)
            ->map(fn (array $count, string $kind): array => [$kind, ...$count])->values()->all());

        if (! $apply) {
            $this->warn('Dry run: nothing was copied or changed. Run with --apply to move them.');
        } elseif (! $this->option('delete-originals')) {
            $this->info('Originals kept. Once the business has checked its files, run again with --apply --delete-originals.');
        }

        return self::SUCCESS;
    }

    /**
     * Moves one file from before stage 1c (on the public disk) to its place in
     * the business's folder on $toDisk, checking the copy. A file already
     * moved has its original deleted when --delete-originals is given.
     * Returns the path to point the record at, or null to leave it.
     */
    private function relocate(string $kind, string $prefix, string $current, string $toDisk, bool $apply): ?string
    {
        $this->counts[$kind] ??= [0, 0, 0];
        $moved = str_starts_with($current, $prefix);
        $original = $moved ? substr($current, strlen($prefix)) : $current;
        $target = $prefix.$original;
        $source = Storage::disk(TenantFiles::PUBLIC_DISK);
        $destination = Storage::disk($toDisk);

        if ($moved) {
            $this->counts[$kind][2]++;

            if ($apply && $this->option('delete-originals')
                && $source->exists($original) && $destination->exists($target)
                && $destination->size($target) === $source->size($original)) {
                $source->delete($original);
            }

            return null;
        }

        if (! $source->exists($original)) {
            $this->counts[$kind][1]++;

            return null;
        }

        $this->counts[$kind][0]++;

        if (! $apply) {
            return null;
        }

        if (! $destination->exists($target)) {
            $destination->put($target, $source->get($original));
        }

        if ($destination->size($target) !== $source->size($original)) {
            throw new \RuntimeException("The copy of {$original} doesn't match the original; its record was not changed.");
        }

        if ($this->option('delete-originals')) {
            $source->delete($original);
        }

        return $target;
    }
}
