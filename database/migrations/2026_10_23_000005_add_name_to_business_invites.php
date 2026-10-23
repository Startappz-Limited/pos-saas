<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: adding a user is one invite flow. The inviter may name the person
 * (optional; the invitee fills it in when it is blank), and invites now expire
 * after 72 hours: pending invites made under the 7-day rule are cut back to
 * 72 hours from when they were sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_invites', function (Blueprint $table) {
            $table->string('name')->nullable()->after('email');
        });

        DB::table('business_invites')->whereNull('accepted_at')->whereNull('revoked_at')
            ->orderBy('id')->select('id', 'created_at', 'expires_at')->each(function ($invite): void {
                $limit = Carbon::parse($invite->created_at)->addHours(72);

                if (Carbon::parse($invite->expires_at)->gt($limit)) {
                    DB::table('business_invites')->where('id', $invite->id)->update(['expires_at' => $limit]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('business_invites', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
