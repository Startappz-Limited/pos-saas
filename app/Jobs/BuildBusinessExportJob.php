<?php

namespace App\Jobs;

use App\Enums\BusinessExportStatus;
use App\Models\Business;
use App\Models\BusinessExport;
use App\Notifications\BusinessExportReady;
use App\Services\BusinessExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds a business export and emails the owner a link to it.
 *
 * A pre-closing export is kept for Business::EXPORT_VALID_DAYS; the final
 * export (built when closing is requested) is kept until the business is
 * purged.
 */
class BuildBusinessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $exportId) {}

    public function handle(BusinessExporter $exporter): void
    {
        $export = BusinessExport::find($this->exportId);

        if (! $export?->isPending()) {
            return;
        }

        $business = Business::withTrashed()->find($export->business_id);

        try {
            $path = $exporter->build($export);
        } catch (Throwable $e) {
            // Recorded here as well as in failed(): on a sync queue failed() never runs
            $this->failed($e);
            report($e);

            return;
        }

        $export->update([
            'status' => BusinessExportStatus::READY,
            'path' => $path,
            'size' => Storage::disk(BusinessExport::DISK)->size($path),
            'completed_at' => now(),
            'expires_at' => $export->is_final && $business?->purge_after
                ? $business->purge_after
                : now()->addDays(Business::EXPORT_VALID_DAYS),
            'error' => null,
        ]);

        $business?->owner?->notify(new BusinessExportReady($export));
    }

    public function failed(?Throwable $exception): void
    {
        BusinessExport::whereKey($this->exportId)->update([
            'status' => BusinessExportStatus::FAILED,
            'error' => $exception ? mb_substr($exception->getMessage(), 0, 1000) : null,
        ]);
    }
}
