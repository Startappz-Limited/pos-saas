<?php

namespace App\Http\Controllers;

use App\Models\BusinessIntegration;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The business's own SMS and WhatsApp Cloud credentials (stage 3). Write-only:
 * saved encrypted, never shown again; leaving a field blank keeps the saved
 * value.
 */
class BusinessIntegrationController extends Controller
{
    /** Fields per type: name => required on first save. */
    private const FIELDS = [
        BusinessIntegration::TYPE_SMS => ['api_url' => true, 'api_token' => true, 'sender_id' => false],
        BusinessIntegration::TYPE_WHATSAPP_CLOUD => ['api_url' => true, 'api_token' => true, 'phone_number_id' => false],
    ];

    public function __construct(private TenantContext $tenant) {}

    public function edit(Request $request): View
    {
        $this->authorizeSettings($request);

        return view('business.integrations', [
            'saved' => BusinessIntegration::query()->whereNull('shop_id')->whereIn('type', array_keys(self::FIELDS))->get()->keyBy('type'),
            'fields' => self::FIELDS,
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $this->authorizeSettings($request);
        abort_unless(isset(self::FIELDS[$type]), 404);

        $existing = BusinessIntegration::query()->whereNull('shop_id')->where('type', $type)->first();

        $rules = [];
        foreach (self::FIELDS[$type] as $field => $required) {
            $rules[$field] = [$existing !== null || ! $required ? 'nullable' : 'required', 'string', 'max:500'];
        }
        $rules['api_url'][] = 'url:https';

        $validated = $request->validate($rules);

        // Blank fields keep what is saved.
        $credentials = array_merge($existing?->credentials ?? [], array_filter($validated, fn ($value): bool => filled($value)));

        $values = ['credentials' => $credentials, 'status' => BusinessIntegration::STATUS_ACTIVE, 'updated_by' => $request->user()->id];

        $existing !== null
            ? $existing->update($values)
            : BusinessIntegration::create([...$values, 'type' => $type, 'created_by' => $request->user()->id]);

        return redirect()->route('business.integrations.edit')->with('success', __('Saved.'));
    }

    public function destroy(Request $request, string $type): RedirectResponse
    {
        $this->authorizeSettings($request);
        abort_unless(isset(self::FIELDS[$type]), 404);

        BusinessIntegration::query()->whereNull('shop_id')->where('type', $type)->delete();

        return redirect()->route('business.integrations.edit')->with('success', __('Disconnected.'));
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()->can('settings.update') || $request->user()->can('settings.full-access'), 403);
    }
}
