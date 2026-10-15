<?php

namespace App\Http\Controllers;

use App\Models\BaileysSession;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a private business file (stage 1c) to a signed-in member of that
 * business. Anything outside the current business's folder is a 404, and
 * WhatsApp media only for sessions in shops the viewer may access.
 */
class TenantFileController extends Controller
{
    public function __invoke(string $path, TenantFiles $files): StreamedResponse
    {
        abort_unless($files->owns($path), 404);

        // {business}/baileys/{session uuid}/...: the session's shop decides.
        if (preg_match('#^[^/]+/baileys/([^/]+)/#', $path, $match) === 1) {
            abort_unless(BaileysSession::query()->where('uuid', $match[1])->exists(), 404);
        }

        $disk = Storage::disk(TenantFiles::PRIVATE_DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
