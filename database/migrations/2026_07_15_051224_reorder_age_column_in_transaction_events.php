<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || !Schema::hasColumn('transaction_events', 'age')) {
            return;
        }

        DB::statement('ALTER TABLE transaction_events MODIFY COLUMN age INT UNSIGNED NULL AFTER full_name');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || !Schema::hasColumn('transaction_events', 'age')) {
            return;
        }

        DB::statement('ALTER TABLE transaction_events MODIFY COLUMN age INT UNSIGNED NULL AFTER address');
    }
};
