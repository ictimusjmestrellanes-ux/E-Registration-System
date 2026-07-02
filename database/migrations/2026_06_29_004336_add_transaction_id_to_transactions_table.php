<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'transaction_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transaction_id', 30)->unique()->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions') || !Schema::hasColumn('transactions', 'transaction_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
        });
    }
};
