<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Http\Requests\UpdateBusinessSettingsRequest;
use App\Models\Business;
use App\Models\Shop;
use App\Models\User;
use App\Support\Business\DefaultShopAudit;
use App\Support\Currency;
use App\Support\Sales\VoidWindow;
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
 * stock adjustment approval threshold and the sale void window (stage 10,
 * steps 1g and 2), each change audited with its old and new values.
 */
class BusinessSettingsController extends Controller
{
    /**
     * Settings in businesses.settings that only the owner changes, by key.
     */
    private const OWNER_SETTINGS = [
        'stock.adjustment_approval_threshold' => 'the adjustment approval threshold',
        'sales.void_window' => 'the void time limit',
    ];

    public function __construct(private TenantContext $tenant, private TenantFiles $files) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);

        return view('business.settings', [
            'business' => $this->tenant->business(),
            'adjustmentThreshold' => AdjustmentApproval::threshold($this->tenant->business()),
            'voidWindow' => VoidWindow::setting($this->tenant->business()),
            'currencies' => Currency::options(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);

        $business = $this->tenant->business();
        $data = collect($request->validated())->except(['logo', 'remove_logo', 'adjustment_approval_threshold', 'void_window', 'void_window_minutes'])->all();

        // Settings kept in businesses.settings: [key => [old, new]], absent fields unchanged.
        $changes = array_filter([
            'stock.adjustment_approval_threshold' => [
                $old = AdjustmentApproval::threshold($business),
                $request->filled('adjustment_approval_threshold') ? round((float) $request->validated('adjustment_approval_threshold'), 2) : $old,
            ],
            'sales.void_window' => [
                $old = VoidWindow::setting($business),
                match ($request->validated('void_window')) {
                    null => $old,
                    'minutes' => (int) $request->validated('void_window_minutes'),
                    default => $request->validated('void_window'),
                },
            ],
        ], fn (array $change): bool => $change[0] !== $change[1]);

        // Who may skip a second person, and how long a sale can be voided, are
        // the owner's decisions (3 November 2026), not settings.update's alone.
        abort_if($changes !== [] && ! $request->user()->isBusinessOwner(), 403, __('Only the owner can change these settings: :names.', [
            'names' => implode(', ', array_map(fn (string $key): string => __(self::OWNER_SETTINGS[$key]), array_keys($changes))),
        ]));

        if ($changes !== []) {
            $settings = $business->settings ?? [];
            foreach ($changes as $key => [, $new]) {
                data_set($settings, $key, $new);
            }
            $data['settings'] = $settings;
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo($business->logo_path);
            $data['logo_path'] = $this->files->storePublic($request->file('logo'), 'branding', 'logo');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($business->logo_path);
            $data['logo_path'] = null;
        }

        if (array_key_exists('default_shop_id', $data)) {
            $data['default_shop_id'] = $data['default_shop_id'] !== null ? (int) $data['default_shop_id'] : null;
        }
        $previousDefaultShopId = $business->default_shop_id;

        $business->update($data);

        foreach ($changes as $key => [$old, $new]) {
            $this->auditSetting($request->user(), $business, $key, $old, $new);
        }

        if (array_key_exists('default_shop_id', $data)) {
            DefaultShopAudit::record($business, $previousDefaultShopId, $data['default_shop_id']);
        }

        return redirect()->route('business.settings.edit')->with('success', __('Business settings saved.'));
    }

    private function auditSetting(User $user, Business $business, string $key, mixed $old, mixed $new): void
    {
        AuditableEvent::dispatch(
            auditableType: Business::class,
            auditableId: $business->id,
            auditableUuid: (string) $business->uuid,
            event: AuditEvent::UPDATED,
            oldValues: [$key => $old],
            newValues: [$key => $new],
            userId: $user->id,
            userName: $user->name,
            userEmail: $user->email,
            tags: ['settings', explode('.', $key)[0]],
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
