<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a platform operator (decision 4.4 and 4.7, Phase 5): a login
 * outside every business, the only user Gate::before lets do everything. The
 * first operator is made by the console command, never seeded.
 *
 * An operator is never a business member, so an email that belongs to a
 * member is refused. An existing login with no membership may be promoted
 * instead, when asked for explicitly.
 */
class CreatePlatformOperator
{
    public function execute(string $name, string $email, ?string $password, bool $promoteExisting = false): User
    {
        return app(TenantContext::class)->withoutBusiness(fn (): User => DB::transaction(function () use ($name, $email, $password, $promoteExisting): User {
            $existing = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->memberships()->exists()) {
                    throw new DomainException('That email belongs to a member of a business. A platform operator is never a business member: use another email.');
                }

                if (! $promoteExisting) {
                    throw new DomainException('A login with that email exists. Run again with --promote to make it a platform operator.');
                }

                $existing->forceFill(['is_platform_operator' => true, 'status' => 'active', 'default_business_id' => null])->save();

                return $existing;
            }

            if ($password === null) {
                throw new DomainException('A new platform operator needs a password.');
            }

            $operator = new User(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'status' => 'active']);
            $operator->forceFill(['is_platform_operator' => true, 'email_verified_at' => now(), 'default_business_id' => null])->save();

            return $operator;
        }));
    }
}
