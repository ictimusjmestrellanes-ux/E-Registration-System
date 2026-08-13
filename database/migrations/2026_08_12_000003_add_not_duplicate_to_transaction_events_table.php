<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transaction_events', 'not_duplicate')) {
            Schema::table('transaction_events', function (Blueprint $table) {
                $table->boolean('not_duplicate')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaction_events', 'not_duplicate')) {
            Schema::table('transaction_events', function (Blueprint $table) {
                $table->dropColumn('not_duplicate');
            });
        }
    }
};