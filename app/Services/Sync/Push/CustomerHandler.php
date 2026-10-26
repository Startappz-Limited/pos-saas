<?php

namespace App\Services\Sync\Push;

use App\Models\Customer;
use App\Models\SyncIssue;
use App\Support\Sync\SyncIssues;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A customer a till created offline (Phase 7, decision 4.9). Customers belong
 * to the business, so one whose phone matches an existing customer is saved
 * and flagged for an owner or manager to merge or keep, never merged here.
 * Credit is never opened offline.
 */
final class CustomerHandler implements PushHandler
{
    public function __construct(private SyncIssues $issues) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_pin' => ['nullable', 'string', 'max:32'],
            'customer_type' => ['nullable', Rule::in(['retail', 'wholesale'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $existing = filled($data['phone'] ?? null) ? Customer::query()->wherePhone($data['phone'])->first() : null;

        $customer = Customer::create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'client_uuid' => $item->clientUuid,
            'code' => $this->code(),
            'shop_id' => $item->register->shop_id,
            'customer_type' => $data['customer_type'] ?? 'retail',
            'allow_credit' => false,
            'credit_limit' => 0,
            'credit_balance' => 0,
            'status' => 'active',
            'created_by' => $item->user->id,
        ]);

        if ($existing !== null) {
            $this->issues->record(SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER, 'customer:'.$customer->uuid, [
                'customer' => (string) $customer->uuid,
                'duplicate_of' => (string) $existing->uuid,
                'phone' => $customer->phone,
            ], $item->register);
        }

        return [
            'customer_uuid' => $customer->uuid,
            'customer_id' => $customer->id,
            'flags' => $existing ? ['possible_duplicate'] : [],
            'possible_duplicate_of' => $existing?->uuid,
        ];
    }

    private function code(): string
    {
        do {
            $code = 'CUS-'.strtoupper(Str::random(6));
        } while (Customer::query()->where('code', $code)->exists());

        return $code;
    }
}
