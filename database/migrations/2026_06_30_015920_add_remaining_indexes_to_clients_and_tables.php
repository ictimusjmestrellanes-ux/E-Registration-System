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

        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        return !empty($indexes);
    }

    private function addIndexIfMissing(string $table, string $index, array $columns, array $mysqlColumnSql = []): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $columnSql = $mysqlColumnSql ?: array_map(fn ($column) => "`{$column}`", $columns);
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` (" . implode(', ', $columnSql) . ')');
            return;
        }

        if ($driver === 'sqlite') {
            $columnSql = array_map(fn ($column) => "\"{$column}\"", $columns);
            DB::statement("CREATE INDEX \"{$index}\" ON \"{$table}\" (" . implode(', ', $columnSql) . ')');
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    public function up(): void
    {
        $this->addIndexIfMissing('clients', 'idx_clients_fingerprint_template', ['fingerprint_template'], ['`fingerprint_template`(255)']);
        $this->addIndexIfMissing('clients', 'idx_clients_created_at', ['created_at']);
        $this->addIndexIfMissing('clients', 'idx_clients_last_name', ['last_name']);
        $this->addIndexIfMissing('clients', 'idx_clients_name', ['last_name', 'first_name']);
        $this->addIndexIfMissing('activity_logs', 'idx_activity_logs_created_at', ['created_at']);
        $this->addIndexIfMissing('transaction_history', 'idx_transaction_history_created_at', ['created_at']);
    }

    public function down(): void
    {
    }
};
