<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables owned by a business rather than by one shop. Shop-owned tables
     * already carry shop_id and are isolated through the shop's business.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'users',
        'shops',
        'suppliers',
        'categories',
        'attributes',
        'pricing_rules',
        'expense_categories',
        'delivery_companies',
        'sale_sources',
    ];

    /**
     * Add a nullable business_id to each table, then put every existing row in
     * one default business so the current data stays visible to its owner.
     *
     * The column stays nullable: rows with no business are visible only to a
     * super-admin, which is the safe failure mode for anything missed here.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('business_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('businesses')
                    ->nullOnDelete();
            });
        }

        $this->backfillDefaultBusiness();
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_id');
            });
        }
    }

    /**
     * Existing data predates businesses and all belongs to one owner, so it
     * goes into a single business owned by the first admin, if there is one.
     * Super-admins are platform operators and stay outside any business.
     */
    private function backfillDefaultBusiness(): void
    {
        $hasData = DB::table('shops')->exists() || DB::table('users')->exists();

        if (! $hasData) {
            return;
        }

        $superAdminIds = $this->userIdsWithRole('super-admin');
        $ownerId = $this->userIdsWithRole('admin')[0] ?? null;

        $businessId = DB::table('businesses')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => config('app.name', 'My Business'),
            'owner_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $tableName) {
            $query = DB::table($tableName)->whereNull('business_id');

            if ($tableName === 'users' && $superAdminIds !== []) {
                $query->whereNotIn('id', $superAdminIds);
            }

            $query->update(['business_id' => $businessId]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function userIdsWithRole(string $role): array
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return [];
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->orderBy('model_has_roles.model_id')
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
};
