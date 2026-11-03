<?php

use App\Actions\Platform\GrantTemplatePermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Approving stock adjustments is a permission of its own (stage 10, step 1g
 * follow-up): the owner holds every permission, and the manager template holds
 * `stock-adjustments.approve`. Add-only: gives it to the manager template and
 * any business copy lacking it, skipping a copy whose owner removed it, each
 * grant audited. The inventory-clerk keeps create without approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gained = app(GrantTemplatePermissions::class)->execute(
            'manager',
            ['stock-adjustments.approve'],
            'Managers approve stock adjustments (decided 3 November 2026)',
        );

        if (! app()->runningUnitTests()) {
            echo '  Manager stock-adjustments.approve granted to '.count($gained)." role(s).\n";
        }
    }

    public function down(): void
    {
        // Add-only: an owner removes it from a role on purpose.
    }
};
