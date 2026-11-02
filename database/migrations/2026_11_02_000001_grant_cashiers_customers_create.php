<?php

use App\Actions\Platform\GrantTemplatePermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Cashiers may add a customer (decided 2 November 2026, hotfix fix/api-authz).
 * The flat API now checks `customers.create` on `POST /api/customers`, as the
 * web does, and a cashier who sells on credit needs the customer first.
 *
 * `RoleSeeder` lists it for the cashier template, but on an existing database
 * the seeder only grants permissions created after the role was last saved,
 * and copies only those created after the copy (stage 10, gap 11), so neither
 * the template nor any business's copy would get this old permission. This
 * migration grants it to the shared cashier template and to every business's
 * copy of it, adding only, each grant audited (GrantTemplatePermissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(GrantTemplatePermissions::class)->execute('cashier', ['customers.create'], 'Cashiers may add a customer (decided 2 November 2026)');
    }

    public function down(): void
    {
        // Nothing is taken back: a business may have relied on it since.
    }
};
