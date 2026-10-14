<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SocialAccount::setCredentials() stores an encrypted string
     * (Crypt::encryptString), which is not JSON. MySQL's JSON type and
     * MariaDB's JSON alias (CHECK json_valid) both reject it, so connecting a
     * social account failed on every real database; only SQLite accepted it.
     *
     * Changing the column type keeps every stored value: the only values the
     * JSON column could ever have accepted are NULL and valid JSON text.
     */
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->text('credentials')->nullable()->change();
        });
    }

    /**
     * Encrypted credentials can't go back into a JSON column, so rolling back
     * is refused while any are stored rather than losing them.
     */
    public function down(): void
    {
        $encrypted = DB::table('social_accounts')
            ->whereNotNull('credentials')
            ->pluck('credentials')
            ->reject(fn (string $value): bool => json_validate($value))
            ->count();

        if ($encrypted > 0) {
            throw new RuntimeException(
                "Cannot change social_accounts.credentials back to JSON: {$encrypted} row(s) hold encrypted credentials that aren't JSON."
            );
        }

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->json('credentials')->nullable()->change();
        });
    }
};
