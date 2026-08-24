<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transaction_events', 'transferred_transaction_id')) {
            Schema::table('transaction_events', function (Blueprint $table) {
                $table->foreignId('transferred_transaction_id')
                    ->nullable()
                    ->constrained('transaction_history')
                    ->nullOnDelete();

                $table->unique('transferred_transaction_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaction_events', 'transferred_transaction_id')) {
            Schema::table('transaction_events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('transferred_transaction_id');
            });
        }
    }
};
