<?php

namespace App\Actions\Shop;

use App\Contracts\MpesaGateway;
use App\Models\BusinessIntegration;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a shop's own M-Pesa Till or Paybill and the account details behind it
 * (stage 3, open question 2; shortcode ownership, decision 4.3).
 *
 * A shortcode belongs to one shop on the platform, and is accepted only after
 * a test query to the M-Pesa API with the shop's own details succeeds.
 * Nothing is stored otherwise. The details are encrypted and never shown
 * again; leaving them blank keeps the stored ones when the shortcode is
 * unchanged.
 */
class SaveShopMpesaAccount
{
    public function __construct(private MpesaGateway $gateway) {}

    /**
     * @param  array{shortcode: string, type: string, consumer_key?: ?string, consumer_secret?: ?string, passkey?: ?string}  $data
     */
    public function execute(Shop $shop, array $data, ?int $userId = null): Shop
    {
        $shortcode = preg_replace('/\s+/', '', $data['shortcode']);
        $existing = BusinessIntegration::query()->where('shop_id', $shop->id)->where('type', BusinessIntegration::TYPE_MPESA)->first();

        $credentials = array_filter([
            'consumer_key' => $data['consumer_key'] ?? null,
            'consumer_secret' => $data['consumer_secret'] ?? null,
            'passkey' => $data['passkey'] ?? null,
        ], fn ($value): bool => filled($value));

        // Blank fields keep what is stored, for the same number.
        if ($existing !== null && $shop->mpesa_shortcode === $shortcode) {
            $credentials += array_filter($existing->credentials ?? [], fn ($value): bool => filled($value));
        }

        foreach (['consumer_key', 'consumer_secret'] as $required) {
            if (empty($credentials[$required])) {
                throw ValidationException::withMessages([$required => [__('The M-Pesa account details are needed to prove the number.')]]);
            }
        }

        if ($this->takenElsewhere($shop, $shortcode)) {
            throw ValidationException::withMessages(['shortcode' => [__('That number is already registered to another shop.')]]);
        }

        $check = $this->gateway->verify($shortcode, $data['type'], $credentials);

        if (! $check->ok) {
            throw ValidationException::withMessages(['shortcode' => [__('M-Pesa did not accept these details: :reason', ['reason' => $check->error ?? __('unknown error')])]]);
        }

        return DB::transaction(function () use ($shop, $shortcode, $data, $credentials, $existing, $userId): Shop {
            $shop->forceFill([
                'mpesa_shortcode' => $shortcode,
                'mpesa_shortcode_type' => $data['type'],
                'mpesa_verified_at' => now(),
            ])->save();

            $values = [
                'credentials' => $credentials,
                'status' => BusinessIntegration::STATUS_ACTIVE,
                'verified_at' => now(),
                'last_error' => null,
                'updated_by' => $userId,
            ];

            if ($existing !== null) {
                $existing->update($values);
            } else {
                BusinessIntegration::create([...$values, 'shop_id' => $shop->id, 'type' => BusinessIntegration::TYPE_MPESA, 'created_by' => $userId]);
            }

            return $shop;
        });
    }

    /**
     * Platform-wide: the number may belong to a shop of another business, so
     * this reads past the tenant scope on purpose (a query-builder query).
     */
    private function takenElsewhere(Shop $shop, string $shortcode): bool
    {
        return DB::table('shops')->where('mpesa_shortcode', $shortcode)->where('id', '!=', $shop->id)->exists();
    }
}
