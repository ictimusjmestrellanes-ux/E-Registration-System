<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $existingIndex) {
                if (($existingIndex->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
    }

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'azure_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('azure_id')->nullable()->after('google_id');
            });
        }

        if (!$this->indexExists('users', 'users_azure_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('azure_id');
            });
        }

        if (!Schema::hasColumn('users', 'auth_provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('auth_provider')->nullable()->after('azure_id');
            });
        }

        if (!Schema::hasColumn('users', 'provider_avatar')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('provider_avatar')->nullable()->after('avatar');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['azure_id', 'auth_provider', 'provider_avatar'],
            fn (string $column) => Schema::hasColumn('users', $column)
        ));

        if (Schema::hasColumn('users', 'azure_id') && $this->indexExists('users', 'users_azure_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['azure_id']);
            });
        }

        if (empty($columns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn($columns);
        });
    }
};
