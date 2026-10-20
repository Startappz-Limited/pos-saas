<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The founding (primary) owner of a business (decision 4.4, stage 3): the one
 * owner other owners can't remove or demote, and the only one who hands the
 * business over. Set at signup and by a handover.
 *
 * Existing businesses get their earliest active owner. Nullable: if the login
 * is ever deleted the business keeps working and any owner may hand it over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('primary_owner_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        $adminRoleIds = DB::table('roles')->where('name', 'admin')->pluck('id');

        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $owner = DB::table('model_has_roles')
                ->join('business_user', function ($join) use ($businessId): void {
                    $join->on('business_user.user_id', '=', 'model_has_roles.model_id')->where('business_user.business_id', $businessId);
                })
                ->where('model_has_roles.business_id', $businessId)
                ->where('model_has_roles.model_type', (new User)->getMorphClass())
                ->whereIn('model_has_roles.role_id', $adminRoleIds)
                ->where('business_user.status', 'active')
                ->orderBy('business_user.joined_at')->orderBy('model_has_roles.model_id')
                ->value('model_has_roles.model_id');

            if ($owner !== null) {
                DB::table('businesses')->where('id', $businessId)->update(['primary_owner_id' => $owner]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_owner_id');
        });
    }
};
