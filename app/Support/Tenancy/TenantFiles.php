<?php

namespace App\Support\Tenancy;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The current business's files (stage 1c): every path starts with the
 * business's uuid, and each business has a storage quota.
 *
 * Private files (WhatsApp media, profile photos) live on the `tenant` disk,
 * outside the web root, and are served only through the authorized
 * tenant-files.show route. Product images stay on the public disk under the
 * same prefix: the Flutter app and the online stores load them without
 * signing in (decided 15 October 2026).
 */
class TenantFiles
{
    public const PRIVATE_DISK = 'tenant';

    public const PUBLIC_DISK = 'public';

    private const USAGE_TTL_SECONDS = 600;

    public function __construct(private TenantContext $tenant) {}

    /**
     * The current business's folder: its uuid. Refused with no business set.
     */
    public function prefix(): string
    {
        $business = $this->tenant->business() ?? throw MissingTenantContext::forModel('file');

        return (string) $business->uuid;
    }

    /**
     * $relative inside the current business's folder.
     */
    public function path(string $relative): string
    {
        return $this->prefix().'/'.ltrim($relative, '/');
    }

    /**
     * Whether $path is inside the current business's folder.
     */
    public function owns(string $path): bool
    {
        return $this->tenant->has()
            && ! str_contains($path, '..')
            && str_starts_with($path, $this->prefix().'/');
    }

    /**
     * Stores an upload privately in $folder, after checking the quota
     * (a validation error on $field when it doesn't fit). Returns its path.
     */
    public function storePrivate(UploadedFile $file, string $folder, string $field = 'file'): string
    {
        return $this->store($file, $folder, self::PRIVATE_DISK, $field);
    }

    /**
     * Stores an upload on the public disk in $folder (product images).
     */
    public function storePublic(UploadedFile $file, string $folder, string $field = 'image'): string
    {
        return $this->store($file, $folder, self::PUBLIC_DISK, $field);
    }

    /**
     * Writes $bytes to $relative on $disk, unless they don't fit the quota.
     * Returns the path, or null when over quota.
     */
    public function putIfRoom(string $relative, string $bytes, string $disk = self::PRIVATE_DISK): ?string
    {
        if (! $this->hasRoom(strlen($bytes))) {
            return null;
        }

        $path = $this->path($relative);
        Storage::disk($disk)->put($path, $bytes);
        $this->forgetUsage();

        return $path;
    }

    /**
     * The full URL of a public file (a product image), for the Flutter app
     * and the online stores. A value that is already a URL (an image a store
     * sync brought in) is returned as it is; none gives null.
     */
    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return Storage::disk(self::PUBLIC_DISK)->url(ltrim($path, '/'));
    }

    /**
     * The URL a signed-in member opens a private file at.
     */
    public function url(string $path): string
    {
        return route('tenant-files.show', ['path' => $path]);
    }

    public function quota(): int
    {
        $business = $this->tenant->business();
        $own = $business?->settings['files_quota_bytes'] ?? null;

        return (int) ($own ?? config('tenancy.files_quota_bytes'));
    }

    /**
     * Bytes the business stores across both disks; cached for a few minutes
     * and forgotten whenever this service writes.
     */
    public function usage(): int
    {
        return (int) Cache::remember($this->tenant->cacheKey('files:usage'), self::USAGE_TTL_SECONDS, function (): int {
            $bytes = 0;
            foreach ([self::PRIVATE_DISK, self::PUBLIC_DISK] as $disk) {
                foreach (Storage::disk($disk)->allFiles($this->prefix()) as $file) {
                    $bytes += Storage::disk($disk)->size($file);
                }
            }

            return $bytes;
        });
    }

    public function hasRoom(int $bytes): bool
    {
        $quota = $this->quota();

        return $quota <= 0 || $this->usage() + $bytes <= $quota;
    }

    public function forgetUsage(): void
    {
        Cache::forget($this->tenant->cacheKey('files:usage'));
    }

    private function store(UploadedFile $file, string $folder, string $disk, string $field): string
    {
        if (! $this->hasRoom((int) $file->getSize())) {
            throw ValidationException::withMessages([
                $field => __('This business has used its file storage. Delete some files or ask for more space.'),
            ]);
        }

        $path = $file->storeAs($this->path($folder), Str::random(40).'.'.($file->extension() ?: 'bin'), $disk);
        $this->forgetUsage();

        return $path;
    }
}
