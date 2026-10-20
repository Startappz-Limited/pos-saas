<?php

namespace App\Actions\Business;

use App\Models\OwnerConfirmation;
use App\Models\User;
use App\Notifications\OwnerConfirmationCode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;

/**
 * Emails an owner a one-time code for $purpose in the current business
 * (stage 3); any earlier unused code for the same purpose stops working.
 */
class SendOwnerConfirmationCode
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $owner, string $purpose, array $payload = []): OwnerConfirmation
    {
        OwnerConfirmation::query()->where('user_id', $owner->id)->where('purpose', $purpose)->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);

        $confirmation = OwnerConfirmation::create([
            'user_id' => $owner->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(OwnerConfirmation::EXPIRES_AFTER_MINUTES),
        ]);

        $owner->notify(new OwnerConfirmationCode($code, $purpose, $this->tenant->business()->name));

        return $confirmation;
    }

    /**
     * The owner's live code for $purpose, if any.
     */
    public function pending(User $owner, string $purpose): ?OwnerConfirmation
    {
        return OwnerConfirmation::query()->where('user_id', $owner->id)->where('purpose', $purpose)
            ->whereNull('used_at')->where('expires_at', '>', now())->where('attempts', '<', OwnerConfirmation::MAX_ATTEMPTS)
            ->latest('id')->first();
    }
}
