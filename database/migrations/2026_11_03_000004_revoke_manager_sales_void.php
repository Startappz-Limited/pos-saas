<?php

use App\Actions\Platform\RevokeTemplatePermissions;
use App\Support\Authorization\DeliberateGrants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voiding is the owner's by default (decided 27 October 2026, stage 10, step
 * 2). Takes `sales.void` off the manager template and every business's copy of
 * it, recorded in `role_revoked_permissions` so `permissions:sync` never gives
 * it back, each change audited. Owners can give it back to any role from the
 * role screens; custom roles a business made itself keep what the owner gave
 * them.
 *
 * A copy whose owner gave it `sales.void` on purpose keeps it (decided 3
 * November 2026): the audit trail shows the attach by a person
 * (DeliberateGrants). Copies that have it from the template show none. With no
 * readable audit trail every copy loses it, and the count of kept copies is 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('roles')->whereNull('business_id')->where('name', 'manager')->value('id');
        $copies = $template === null ? [] : DB::table('roles')->where('template_id', $template)->pluck('id')->all();
        $kept = app(DeliberateGrants::class)->roles('sales.void', $copies);

        $result = app(RevokeTemplatePermissions::class)->execute(
            'manager',
            ['sales.void'],
            'Voiding is the owner\'s by default (decided 27 October 2026)',
            $kept,
        );

        if (! app()->runningUnitTests()) {
            echo "  Manager sales.void: {$result['before']} grant(s) before, {$result['after']} after, across ".count($result['roles']).' role(s); '.count($kept)." copy(ies) kept, granted by their owner.\n";
        }
    }

    public function down(): void
    {
        // Not given back automatically: an owner gives voiding to a role on purpose.
    }
};
