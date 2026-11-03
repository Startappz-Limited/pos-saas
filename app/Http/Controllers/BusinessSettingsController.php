<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Http\Requests\UpdateBusinessSettingsRequest;
use App\Models\Business;
use App\Models\User;
use App\Support\Currency;
use App\Support\Stock\AdjustmentApproval;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The business's own name, logo, receipt text, currency, timezone and invoice
 * prefix (stage 3), which replace the platform's on receipts and PDFs; and the
 * stock adjustment approval threshold (stage 10, step 1g), audited.
 */
class BusinessSettingsController extends Controller
{
    public function __construct(private TenantContext $tenant, private TenantFiles $files) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);

        return view('business.settings', [
            'business' => $this->tenant->business(),
            'adjustmentThreshold' => AdjustmentApproval::threshold($this->tenant->business()),
            'currencies' => Currency::options(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);

        $business = $this->tenant->business();
        $data = collect($request->validated())->except(['logo', 'remove_logo', 'adjustment_approval_threshold'])->all();
        $previous = AdjustmentApproval::threshold($business);
        $threshold = $request->filled('adjustment_approval_threshold')
            ? round((float) $request->validated('adjustment_approval_threshold'), 2)
            : $previous;
        if ($threshold !== $previous) {
            $settings = $business->settings ?? [];
            data_set($settings, 'stock.adjustment_approval_threshold', $threshold);
            $data['settings'] = $settings;
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo($business->logo_path);
            $data['logo_path'] = $this->files->storePublic($request->file('logo'), 'branding', 'logo');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($business->logo_path);
            $data['logo_path'] = null;
        }

        $business->update($data);

        if ($threshold !== $previous) {
            $this->auditThreshold($request->user(), $business, $previous, $threshold);
        }

        return redirect()->route('business.settings.edit')->with('success', __('Business settings saved.'));
    }

    private function auditThreshold(User $user, Business $business, float $previous, float $threshold): void
    {
        AuditableEvent::dispatch(
            auditableType: Business::class,
            auditableId: $business->id,
            auditableUuid: (string) $business->uuid,
            event: AuditEvent::UPDATED,
            oldValues: ['stock.adjustment_approval_threshold' => $previous],
            newValues: ['stock.adjustment_approval_threshold' => $threshold],
            userId: $user->id,
            userName: $user->name,
            userEmail: $user->email,
            tags: ['settings', 'stock'],
            businessId: $business->id,
        );
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null && $this->files->owns($path)) {
            Storage::disk(TenantFiles::PUBLIC_DISK)->delete($path);
            $this->files->forgetUsage();
        }
    }
}
