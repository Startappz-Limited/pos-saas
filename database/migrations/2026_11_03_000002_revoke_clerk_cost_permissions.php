<?php

use App\Actions\Platform\RevokeTemplatePermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Clerks count stock; they don't see or set costs (decided 27 October 2026,
 * stage 10, step 1f). Takes `products.set-cost` and `products.view-cost` off
 * the inventory-clerk template and every business's copy of it, recorded in
 * `role_revoked_permissions` so `permissions:sync` never gives them back, each
 * change audited. Custom roles a business made itself keep what the owner gave
 * them. The cashier template never held either.
 */
return new class extends Migration
{
    public function up(): void
    {
        $result = app(RevokeTemplatePermissions::class)->execute(
            'inventory-clerk',
            ['products.set-cost', 'products.view-cost'],
            'Clerks count stock; they don\'t see or set costs (decided 27 October 2026)',
        );

        if (! app()->runningUnitTests()) {
            echo "  Clerk cost permissions: {$result['before']} grant(s) before, {$result['after']} after, across ".count($result['roles'])." role(s).\n";
        }
    }

    public function down(): void
    {
        // Not given back automatically: an owner gives a cost permission to a role on purpose.
    }
};
