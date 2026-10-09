<?php

namespace App\Services\Integration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageDownloadService
{
    /**
     * Download images from URLs and save them locally
     *
     * @param  array  $imageUrls  Array of image URLs to download
     * @param  string  $directory  Directory to save images (e.g., 'products')
     * @return array Array of local paths for successfully downloaded images
     */
    public function downloadImages(array $imageUrls, string $directory = 'products'): array
    {
        $localPaths = [];

        foreach ($imageUrls as $imageUrl) {
            try {
                $localPath = $this->downloadSingleImage($imageUrl, $directory);
                if ($localPath) {
                    $localPaths[] = $localPath;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to download image', [
                    'url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $localPaths;
    }

    /**
     * Download a single image from URL and save locally
     *
     * @param  string  $imageUrl  The image URL to download
     * @param  string  $directory  Directory to save image
     * @return string|null Local path if successful, null on failure
     */
    public function downloadSingleImage(string $imageUrl, string $directory = 'products'): ?string
    {
        try {
            // Skip if already a local path
            if (! str_starts_with($imageUrl, 'http')) {
                return $imageUrl;
            }

            // Download image
            $response = Http::timeout(30)->get($imageUrl);

            if (! $response->successful()) {
                Log::warning('Failed to download image - HTTP error', [
                    'url' => $imageUrl,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $imageContent = $response->body();

            // Determine file extension from URL or content-type
            $extension = $this->getImageExtension($imageUrl, $response->header('Content-Type'));

            // Generate unique filename
            $filename = Str::random(40) . '.' . $extension;
            $path = "{$directory}/{$filename}";

            // Save to storage
            Storage::disk('public')->put($path, $imageContent);

            return $path;
        } catch (\Exception $e) {
            Log::error('Image download exception', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get image extension from URL or content type
     */
    private function getImageExtension(string $url, ?string $contentType): string
    {
        // Try to get extension from URL
        $urlExtension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (in_array(strtolower($urlExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            return strtolower($urlExtension);
        }

        // Fallback to content type
        return match ($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }

    /**
     * Extract image URLs from platform data
     *
     * @param  array  $platformImages  Images array from platform API
     * @param  string  $platform  Platform name (woocommerce or shopify)
     * @return array Array of image URLs
     */
    public function extractImageUrls(array $platformImages, string $platform): array
    {
        $urls = [];

        foreach ($platformImages as $image) {
            $url = match ($platform) {
                'woocommerce' => $image['src'] ?? null,
                'shopify' => $image['src'] ?? null,
                default => null,
            };

            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
