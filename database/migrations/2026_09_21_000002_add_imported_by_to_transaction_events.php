<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transaction_events', 'imported_by')) {
            Schema::table('transaction_events', function (Blueprint $table) {
                $table->string('imported_by', 100)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaction_events', 'imported_by')) {
            Schema::table('transaction_events', function (Blueprint $table) {
                $table->dropColumn('imported_by');
            });
        }
    }
};
