<?php

namespace App\Jobs;

use App\Actions\Business\CopyRoleTemplates;
use App\Actions\Business\ExportBusinessData;
use App\Models\BusinessExport;
use App\Models\User;
use App\Notifications\BusinessExportReady;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Builds a business's data export in the background (stage 3), then emails
 * its owners a download link. Runs for the business it was dispatched for
 * (stage 1c: the worker restores it).
 */
class BuildBusinessExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    public function __construct(public int $exportId) {}

    public function handle(ExportBusinessData $exporter, TenantContext $tenant): void
    {
        $export = BusinessExport::query()->findOrFail($this->exportId);
        $business = $tenant->business();

        $result = $exporter->execute($business, $export);

        $export->update([
            'status' => BusinessExport::STATUS_READY,
            'path' => $result['path'],
            'size' => $result['size'],
            'summary' => $result['summary'],
            'error' => null,
            'completed_at' => now(),
        ]);

        $owners = User::query()->inCurrentBusiness()->role(CopyRoleTemplates::OWNER_ROLE)->get();
        Notification::send($owners, new BusinessExportReady($export, $business->name));
    }

    public function failed(?Throwable $exception): void
    {
        app(TenantContext::class)->bypass(fn () => BusinessExport::query()->whereKey($this->exportId)->update([
            'status' => BusinessExport::STATUS_FAILED,
            'error' => mb_substr((string) $exception?->getMessage(), 0, 500),
        ]));
    }
}
