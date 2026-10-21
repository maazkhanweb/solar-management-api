
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // google_id was already added by the earlier migration.
        // Keep this migration safe to run against the existing database.
        if (!Schema::hasColumn('users', 'google_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('google_id')->nullable()->unique();
            });
        }

        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check'
        );

        DB::statement(
            'ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(255) USING role::text'
        );
    }

    public function down(): void
    {
        // Preserve google_id because the earlier migration owns that column.
    }
};
