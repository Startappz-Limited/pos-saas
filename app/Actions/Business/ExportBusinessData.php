<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\BusinessExport;
use App\Support\Tenancy\BusinessData;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Writes a business's data to a ZIP archive (stage 3): `data/{table}.jsonl`
 * with every row of each of its tables (one JSON object per line), the
 * members' logins, `audit/audit_logs.jsonl`, and every file under
 * `files/private/` and `files/public/`, with a `manifest.json` of counts.
 * Secrets never go in: passwords, remember and API tokens, invite and
 * confirmation code hashes, sessions.
 *
 * The archive is kept on the private disk in the business's folder, so the
 * closure's erasure removes it with everything else.
 */
class ExportBusinessData
{
    /** Columns never written to an export. */
    public const SECRET_COLUMNS = ['password', 'remember_token', 'token_hash', 'code_hash', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Tables never written to an export (beyond the login tables): secrets or the export's own bookkeeping. */
    private const SKIPPED_TABLES = ['owner_confirmations', 'business_exports', 'users'];

    private const CHUNK = 500;

    /**
     * @return array{path: string, size: int, summary: array<string, int>}
     */
    public function execute(Business $business, BusinessExport $export): array
    {
        $data = new BusinessData($business->id);
        $owned = $data->ownedIds();
        $users = $data->ownedUserIds();
        $temp = [];

        $zipPath = tempnam(sys_get_temp_dir(), 'export');
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $summary = [];
        foreach (array_reverse($data->tablesChildrenFirst()) as $table) {
            if (in_array($table, [...BusinessData::LOGIN_TABLES, ...self::SKIPPED_TABLES], true)) {
                continue;
            }

            $rows = $data->hasId($table)
                ? $this->byIds($table, $owned[$table] ?? [])
                : $data->rowsOf($table, [...$owned, 'users' => $users])->get();

            if (($count = $this->writeLines($zip, "data/{$table}.jsonl", $rows, $temp)) > 0) {
                $summary[$table] = $count;
            }
        }

        // Every member's login (not only those erased with the business), without secrets.
        $members = DB::table('users')->whereIn('id', DB::table('business_user')->where('business_id', $business->id)->select('user_id'))->get();
        $summary['users'] = $this->writeLines($zip, 'data/users.jsonl', $members, $temp);

        $summary['audit_logs'] = $this->writeLines($zip, 'audit/audit_logs.jsonl',
            DB::connection('audit')->table('audit_logs')->where('business_id', $business->id)->orderBy('id')->get(), $temp);

        $files = 0;
        foreach (['private' => TenantFiles::PRIVATE_DISK, 'public' => TenantFiles::PUBLIC_DISK] as $label => $disk) {
            foreach (Storage::disk($disk)->allFiles((string) $business->uuid) as $file) {
                if (str_starts_with($file, $business->uuid.'/exports/')) {
                    continue;
                }
                $zip->addFromString("files/{$label}/".substr($file, strlen($business->uuid) + 1), (string) Storage::disk($disk)->get($file));
                $files++;
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'business' => ['uuid' => $business->uuid, 'name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone],
            'generated_at' => now()->toIso8601String(),
            'rows' => $summary,
            'files' => $files,
            'format' => 'One JSON object per line in data/*.jsonl and audit/*.jsonl. Amounts as stored (decimal strings).',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $path = "{$business->uuid}/exports/{$export->uuid}.zip";
        $stream = fopen($zipPath, 'rb');
        Storage::disk(TenantFiles::PRIVATE_DISK)->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $size = (int) filesize($zipPath);

        foreach ([$zipPath, ...$temp] as $file) {
            @unlink($file);
        }

        return ['path' => $path, 'size' => $size, 'summary' => $summary + ['files' => $files]];
    }

    /**
     * @param  list<int|string>  $ids
     * @return \Generator<int, object>
     */
    private function byIds(string $table, array $ids): \Generator
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            foreach (DB::table($table)->whereIn('id', $chunk)->orderBy('id')->get() as $row) {
                yield $row;
            }
        }
    }

    /**
     * Writes $rows, without secret columns, as JSON lines into the archive.
     *
     * @param  iterable<object>  $rows
     * @param  list<string>  $temp  temporary files to remove once the archive is closed
     */
    private function writeLines(ZipArchive $zip, string $name, iterable $rows, array &$temp): int
    {
        $file = tempnam(sys_get_temp_dir(), 'rows');
        $temp[] = $file;
        $handle = fopen($file, 'wb');
        $count = 0;

        foreach ($rows as $row) {
            $row = array_diff_key((array) $row, array_flip(self::SECRET_COLUMNS));
            fwrite($handle, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $count++;
        }
        fclose($handle);

        if ($count > 0) {
            $zip->addFile($file, $name);
        }

        return $count;
    }
}
