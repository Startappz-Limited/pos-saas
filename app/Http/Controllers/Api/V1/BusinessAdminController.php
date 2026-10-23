<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Business\CloseBusiness;
use App\Actions\Business\HandOverBusiness;
use App\Actions\Business\SendOwnerConfirmationCode;
use App\Actions\Product\ImportProductsFromCsv;
use App\Actions\Shop\SaveShopMpesaAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveShopMpesaRequest;
use App\Http\Requests\UpdateBusinessSettingsRequest;
use App\Jobs\BuildBusinessExport;
use App\Models\BusinessExport;
use App\Models\BusinessIntegration;
use App\Models\OwnerConfirmation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Currency;
use App\Support\Onboarding\OnboardingChecklist;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The business-administration flows of stage 3 on `/api/v1`: settings,
 * onboarding, messaging accounts, a shop's M-Pesa, product import, data export,
 * ownership handover and closure. The same rules as the web screens; owners
 * (or the settings permission) only. Responses are `{success, message, data}`.
 */
class BusinessAdminController extends Controller
{
    public function __construct(
        private TenantContext $tenant,
        private SendOwnerConfirmationCode $codes,
        private HandOverBusiness $handover,
        private CloseBusiness $closer,
    ) {}

    public function settings(Request $request): JsonResponse
    {
        $this->settingsOnly($request);

        return $this->ok($this->presentSettings());
    }

    public function updateSettings(UpdateBusinessSettingsRequest $request, TenantFiles $files): JsonResponse
    {
        $this->settingsOnly($request);

        $business = $this->tenant->business();
        $data = collect($request->validated())->except(['logo', 'remove_logo'])->all();

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            if ($business->logo_path !== null && $files->owns($business->logo_path)) {
                Storage::disk(TenantFiles::PUBLIC_DISK)->delete($business->logo_path);
                $files->forgetUsage();
            }
            $data['logo_path'] = $request->hasFile('logo') ? $files->storePublic($request->file('logo'), 'branding', 'logo') : null;
        }

        $business->update($data);

        return $this->ok($this->presentSettings(), __('Business settings saved.'));
    }

    public function onboarding(OnboardingChecklist $checklist): JsonResponse
    {
        return $this->ok(['steps' => collect($checklist->steps())->map(fn (array $step): array => [
            'key' => $step['key'], 'label' => $step['label'], 'done' => $step['done'],
        ])->all(), 'complete' => $checklist->complete()]);
    }

    /**
     * Which messaging accounts are connected. Never the values.
     */
    public function integrations(Request $request): JsonResponse
    {
        $this->settingsOnly($request);

        $saved = BusinessIntegration::query()->whereNull('shop_id')
            ->whereIn('type', [BusinessIntegration::TYPE_SMS, BusinessIntegration::TYPE_WHATSAPP_CLOUD])->get()->keyBy('type');

        return $this->ok(collect([BusinessIntegration::TYPE_SMS, BusinessIntegration::TYPE_WHATSAPP_CLOUD])
            ->mapWithKeys(fn (string $type): array => [$type => ['connected' => $saved->has($type)]])->all());
    }

    public function saveIntegration(Request $request, string $type): JsonResponse
    {
        $this->settingsOnly($request);
        abort_unless(in_array($type, [BusinessIntegration::TYPE_SMS, BusinessIntegration::TYPE_WHATSAPP_CLOUD], true), 404);

        $existing = BusinessIntegration::query()->whereNull('shop_id')->where('type', $type)->first();
        $extra = $type === BusinessIntegration::TYPE_SMS ? 'sender_id' : 'phone_number_id';

        $validated = $request->validate([
            'api_url' => [$existing ? 'nullable' : 'required', 'url:https', 'max:500'],
            'api_token' => [$existing ? 'nullable' : 'required', 'string', 'max:500'],
            $extra => ['nullable', 'string', 'max:500'],
        ]);

        $credentials = array_merge($existing?->credentials ?? [], array_filter($validated, fn ($value): bool => filled($value)));
        $values = ['credentials' => $credentials, 'status' => BusinessIntegration::STATUS_ACTIVE, 'updated_by' => $request->user()->id];

        $existing !== null
            ? $existing->update($values)
            : BusinessIntegration::create([...$values, 'type' => $type, 'created_by' => $request->user()->id]);

        return $this->ok(['connected' => true], __('Saved.'));
    }

    public function deleteIntegration(Request $request, string $type): JsonResponse
    {
        $this->settingsOnly($request);
        abort_unless(in_array($type, [BusinessIntegration::TYPE_SMS, BusinessIntegration::TYPE_WHATSAPP_CLOUD], true), 404);

        BusinessIntegration::query()->whereNull('shop_id')->where('type', $type)->delete();

        return $this->ok(['connected' => false], __('Disconnected.'));
    }

    public function saveMpesa(SaveShopMpesaRequest $request, Shop $shop, SaveShopMpesaAccount $save): JsonResponse
    {
        $this->authorize('update', $shop);

        $shop = $save->execute($shop, $request->validated(), $request->user()->id);

        return $this->ok([
            'shop' => $shop->uuid, 'shortcode' => $shop->mpesa_shortcode, 'type' => $shop->mpesa_shortcode_type,
            'verified_at' => $shop->mpesa_verified_at?->toIso8601String(),
        ], __('M-Pesa details checked and saved.'));
    }

    public function importProducts(Request $request, ImportProductsFromCsv $import): JsonResponse
    {
        // No confirmation screen here: a file with SKUs that already exist is refused.
        $this->authorize('import', Product::class);

        $validated = $request->validate([
            'shop' => ['required', 'string', 'exists:shops,uuid'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $import->execute($request->file('file')->getRealPath(), Shop::query()->where('uuid', $validated['shop'])->firstOrFail(), $request->user()->id);

        return $result['errors'] === []
            ? $this->ok(['imported' => $result['imported'], 'opening_stock' => $result['opening_stock'], 'ignored_columns' => $result['ignored']], __(':count products imported.', ['count' => $result['imported']]))
            : response()->json(['success' => false, 'message' => __('Nothing was imported.'), 'errors' => ['file' => $result['errors']]], 422);
    }

    public function requestExport(Request $request): JsonResponse
    {
        $this->ownersOnly($request);

        $export = BusinessExport::create(['requested_by' => $request->user()->id]);
        BuildBusinessExport::dispatch($export->id);

        return $this->ok(['export' => $export->uuid], __('The export is being prepared. We will email the owners when it is ready.'));
    }

    /**
     * Step 1 of a handover: emails the owner a code naming the co-owner.
     */
    public function handoverCode(Request $request): JsonResponse
    {
        $this->primaryOwnerOnly($request);

        $validated = $request->validate(['owner' => ['required', 'string']]);
        $target = $this->handover->candidates($request->user())->firstWhere('uuid', $validated['owner']);

        abort_if($target === null, 422, __('Ownership can only pass to an existing co-owner of this business.'));

        $this->codes->execute($request->user(), OwnerConfirmation::PURPOSE_HANDOVER, ['owner_id' => $target->id]);

        return $this->ok(null, __('We emailed you a confirmation code.'));
    }

    public function handover(Request $request): JsonResponse
    {
        $this->primaryOwnerOnly($request);

        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $confirmation = $this->codes->pending($request->user(), OwnerConfirmation::PURPOSE_HANDOVER);

        if ($confirmation === null || ! $confirmation->attempt($validated['code'])) {
            return response()->json(['success' => false, 'message' => __('That code is wrong or has expired.'), 'errors' => ['code' => [__('That code is wrong or has expired.')]]], 422);
        }

        $target = $this->handover->candidates($request->user())->firstWhere('id', $confirmation->payload['owner_id'] ?? 0);
        abort_if($target === null, 422, __('That person is no longer an owner of this business.'));

        $this->handover->execute($request->user(), $target);

        return $this->ok(['owner' => $target->uuid], __('Ownership handed over.'));
    }

    public function closureCode(Request $request): JsonResponse
    {
        $this->ownersOnly($request);

        $this->codes->execute($request->user(), OwnerConfirmation::PURPOSE_CLOSE);

        return $this->ok(null, __('We emailed you a confirmation code.'));
    }

    public function close(Request $request): JsonResponse
    {
        $this->ownersOnly($request);

        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $confirmation = $this->codes->pending($request->user(), OwnerConfirmation::PURPOSE_CLOSE);

        if ($confirmation === null || ! $confirmation->attempt($validated['code'])) {
            return response()->json(['success' => false, 'message' => __('That code is wrong or has expired.'), 'errors' => ['code' => [__('That code is wrong or has expired.')]]], 422);
        }

        $closure = $this->closer->start($this->tenant->business(), $request->user());

        // The token is for a business that is now closing: it stops working.
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(['erase_after' => $closure->delete_after->toIso8601String()], __('The business is closing.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSettings(): array
    {
        $business = $this->tenant->business();

        return [
            'name' => $business->name,
            'currency' => $business->currency,
            'currencies' => array_keys(Currency::options()),
            'timezone' => $business->timezone,
            'invoice_prefix' => $business->invoice_prefix,
            'receipt_header' => $business->receipt_header,
            'receipt_footer' => $business->receipt_footer,
            'logo_url' => $business->logo_path ? Storage::disk(TenantFiles::PUBLIC_DISK)->url($business->logo_path) : null,
        ];
    }

    private function settingsOnly(Request $request): void
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);
    }

    private function ownersOnly(Request $request): void
    {
        abort_unless($this->isOwner($request->user()), 403);
    }

    private function primaryOwnerOnly(Request $request): void
    {
        $primary = $this->tenant->business()->primary_owner_id;

        abort_unless($this->isOwner($request->user()) && ($primary === null || $primary === $request->user()->id), 403);
    }

    private function isOwner(?User $user): bool
    {
        return $user !== null && $user->isBusinessOwner();
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
