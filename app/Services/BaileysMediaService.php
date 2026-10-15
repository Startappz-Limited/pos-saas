<?php

namespace App\Services;

use App\Enums\BaileysChatType;
use App\Enums\BaileysMessageType;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns everything about inbound Baileys media on disk: whether we accept it,
 * where it lands, how much a shop is using, and how it gets cleaned up.
 *
 * Chatway Gateway is the first line of defence: it never forwards status or
 * channel media, and sends no bytes for files over 5 MB. This service is the
 * second: whatever the gateway sends, a client phone still cannot exceed the
 * limits enforced here.
 */
class BaileysMediaService
{
    public const ROOT = 'baileys';

    protected const USAGE_TTL_SECONDS = 300;

    /**
     * Persist inbound media bytes, subject to policy.
     *
     * @return array{stored: bool, reason: ?string, url: ?string, size: ?int, path: ?string}
     */
    public function storeInbound(
        BaileysSession $session,
        BaileysChatType $chatType,
        BaileysMessageType $type,
        string $base64,
        ?string $mime = null,
        ?string $filename = null,
        ?string $waMessageId = null,
    ): array {
        if (! config('baileys.media.store_inbound', true)) {
            return $this->skipped('storage_disabled');
        }

        $allowedChatTypes = (array) config('baileys.media.chat_types', ['private']);

        if (! in_array($chatType->value, $allowedChatTypes, true)) {
            return $this->skipped('chat_type_not_allowed');
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false || $bytes === '') {
            return $this->skipped('invalid_payload');
        }

        $size = strlen($bytes);
        $maxBytes = (int) config('baileys.media.max_bytes', 0);

        if ($maxBytes > 0 && $size > $maxBytes) {
            Log::warning('Baileys inbound media rejected: over size cap', [
                'session_key' => $session->session_key,
                'shop_id' => $session->shop_id,
                'size' => $size,
                'max_bytes' => $maxBytes,
            ]);

            return $this->skipped('too_large');
        }

        if ($this->quotaExceeded($session->shop_id, $size)) {
            Log::warning('Baileys inbound media rejected: shop over media quota', [
                'session_key' => $session->session_key,
                'shop_id' => $session->shop_id,
                'usage_bytes' => $this->shopUsageBytes($session->shop_id),
                'quota_bytes' => (int) config('baileys.media.shop_quota_bytes', 0),
            ]);

            return $this->skipped('quota_exceeded');
        }

        $files = app(TenantFiles::class);

        if (! $files->hasRoom($size)) {
            Log::warning('Baileys inbound media rejected: business over its file quota', [
                'session_key' => $session->session_key,
                'shop_id' => $session->shop_id,
            ]);

            return $this->skipped('business_quota_exceeded');
        }

        $waId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($waMessageId ?: Str::random(16)));
        $path = $this->sessionDirectory($session).'/in/'.$waId.'.'.$this->extensionFor($type, $mime, $filename);

        // Private, under the business's folder (stage 1c): served to signed-in
        // members through tenant-files.show, never from the public web root.
        Storage::disk(TenantFiles::PRIVATE_DISK)->put($path, $bytes);
        $this->forgetShopUsage($session->shop_id);
        $files->forgetUsage();

        return [
            'stored' => true,
            'reason' => null,
            'url' => $files->url($path),
            'size' => $size,
            'path' => $path,
        ];
    }

    /**
     * Bytes of stored media attributed to a shop. Cached — this is called on
     * every inbound media message.
     *
     * Counts inbound media only: outbound rows are staff uploads (bounded by
     * request validation), and the quota exists to contain what a linked client
     * phone pushes at us unprompted.
     */
    public function shopUsageBytes(int $shopId): int
    {
        return (int) Cache::remember(
            $this->usageCacheKey($shopId),
            self::USAGE_TTL_SECONDS,
            fn (): int => (int) BaileysMessage::query()
                ->where('shop_id', $shopId)
                ->whereNotNull('media_url')
                ->sum('media_size'),
        );
    }

    public function quotaExceeded(int $shopId, int $incomingBytes = 0): bool
    {
        $quota = (int) config('baileys.media.shop_quota_bytes', 0);

        if ($quota <= 0) {
            return false;
        }

        return ($this->shopUsageBytes($shopId) + $incomingBytes) > $quota;
    }

    public function forgetShopUsage(int $shopId): void
    {
        Cache::forget($this->usageCacheKey($shopId));
    }

    /**
     * The session's media folder on the private disk, inside its business's
     * folder (stage 1c).
     */
    public function sessionDirectory(BaileysSession $session): string
    {
        return app(TenantFiles::class)->path(self::ROOT.'/'.$session->uuid);
    }

    /**
     * Where the session's media lived before stage 1c, on the public disk.
     */
    public function legacySessionDirectory(BaileysSession $session): string
    {
        return self::ROOT.'/'.$session->uuid;
    }

    /**
     * The disk and path a stored media URL points at: a private file served
     * through /files/..., or a public one from before stage 1c (/storage/...).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function locate(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: $url));

        foreach (['/files/' => TenantFiles::PRIVATE_DISK, '/storage/' => TenantFiles::PUBLIC_DISK] as $marker => $disk) {
            $position = strpos($path, $marker);

            if ($position !== false) {
                $relative = ltrim(substr($path, $position + strlen($marker)), '/');

                return str_contains($relative, self::ROOT.'/') && ! str_contains($relative, '..') ? [$disk, $relative] : null;
            }
        }

        return null;
    }

    /**
     * Remove every media file belonging to a session. Called when a session is
     * deleted — previously these files were orphaned on disk forever.
     */
    public function deleteSessionMedia(BaileysSession $session): void
    {
        Storage::disk(TenantFiles::PRIVATE_DISK)->deleteDirectory($this->sessionDirectory($session));
        Storage::disk(TenantFiles::PUBLIC_DISK)->deleteDirectory($this->legacySessionDirectory($session));
        $this->forgetShopUsage($session->shop_id);
    }

    public function extensionFor(BaileysMessageType $type, ?string $mime, ?string $filename): string
    {
        return match (true) {
            $type === BaileysMessageType::Sticker => 'webp',
            $type === BaileysMessageType::Image && $mime === 'image/png' => 'png',
            $type === BaileysMessageType::Image && $mime === 'image/webp' => 'webp',
            $type === BaileysMessageType::Image => 'jpg',
            $type === BaileysMessageType::Video => 'mp4',
            $type === BaileysMessageType::Audio && str_contains((string) $mime, 'mp4') => 'm4a',
            $type === BaileysMessageType::Audio => 'ogg',
            $type === BaileysMessageType::Document => pathinfo((string) $filename, PATHINFO_EXTENSION) ?: 'bin',
            default => 'bin',
        };
    }

    /**
     * @return array{stored: bool, reason: ?string, url: ?string, size: ?int, path: ?string}
     */
    protected function skipped(string $reason): array
    {
        return ['stored' => false, 'reason' => $reason, 'url' => null, 'size' => null, 'path' => null];
    }

    protected function usageCacheKey(int $shopId): string
    {
        return app(TenantContext::class)->cacheKey("baileys:media-usage:{$shopId}");
    }
}
