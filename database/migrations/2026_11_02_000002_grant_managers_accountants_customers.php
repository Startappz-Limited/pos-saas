<?php

use App\Actions\Platform\GrantTemplatePermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * Managers and accountants keep managing customers (decided 2 November 2026,
 * hotfix fix/api-authz). The flat API used to check no permission on its
 * customer writes, so managers and accountants added and edited customers in
 * the app; it now checks `CustomerPolicy`, as the web does, and neither template
 * held any `customers.*` permission.
 *
 * - manager: `customers.create`, `customers.update`, `customers.activate`,
 *   `customers.deactivate` (not delete);
 * - accountant: `customers.create`, `customers.update`;
 * - inventory clerks get nothing.
 *
 * Granted to each shared template and every business's copy of it, adding only,
 * each grant audited (GrantTemplatePermissions); `RoleSeeder` lists them for new
 * installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grants = app(GrantTemplatePermissions::class);

        $grants->execute('manager', ['customers.create', 'customers.update', 'customers.activate', 'customers.deactivate'],
            'Managers keep managing customers (decided 2 November 2026)');
        $grants->execute('accountant', ['customers.create', 'customers.update'],
            'Accountants keep adding and editing customers (decided 2 November 2026)');
    }

    public function down(): void
    {
        // Nothing is taken back: a business may have relied on it since.
    }
};
