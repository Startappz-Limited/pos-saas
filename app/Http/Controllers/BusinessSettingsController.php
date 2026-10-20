<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessSettingsRequest;
use App\Support\Currency;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The business's own name, logo, receipt text, currency, timezone and invoice
 * prefix (stage 3), which replace the platform's on receipts and PDFs.
 */
class BusinessSettingsController extends Controller
{
    public function __construct(private TenantContext $tenant, private TenantFiles $files) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);

        return view('business.settings', [
            'business' => $this->tenant->business(),
            'currencies' => Currency::options(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);

        $business = $this->tenant->business();
        $data = collect($request->validated())->except(['logo', 'remove_logo'])->all();

        if ($request->hasFile('logo')) {
            $this->deleteLogo($business->logo_path);
            $data['logo_path'] = $this->files->storePublic($request->file('logo'), 'branding', 'logo');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($business->logo_path);
            $data['logo_path'] = null;
        }

        $business->update($data);

        return redirect()->route('business.settings.edit')->with('success', __('Business settings saved.'));
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null && $this->files->owns($path)) {
            Storage::disk(TenantFiles::PUBLIC_DISK)->delete($path);
            $this->files->forgetUsage();
        }
    }
}
