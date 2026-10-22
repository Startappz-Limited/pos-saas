<?php

use App\Support\Signup\EmailPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6, signup hardening: the normalised form of every login's email (no
 * +tag, and for Gmail no dots), so one person can't open a second account or
 * a second trial by respelling their address; and the disposable-provider
 * list as data, so it can be updated without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_normalized')->nullable()->index()->after('email');
        });

        DB::table('users')->orderBy('id')->select('id', 'email')->each(function ($user): void {
            DB::table('users')->where('id', $user->id)->update([
                'email_normalized' => EmailPolicy::normalise($user->email),
            ]);
        });

        Schema::create('disposable_email_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->timestamps();
        });

        // The shipped starter list; `signup:load-disposable-domains` adds more later.
        $file = database_path('data/disposable-domains.txt');
        $domains = is_readable($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];

        foreach (array_chunk(array_values(array_unique(array_map(fn ($d) => strtolower(trim($d)), $domains))), 200) as $chunk) {
            DB::table('disposable_email_domains')->insert(array_map(
                fn ($domain) => ['domain' => $domain, 'created_at' => now(), 'updated_at' => now()],
                $chunk,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('disposable_email_domains');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_normalized']);
            $table->dropColumn('email_normalized');
        });
    }
};
