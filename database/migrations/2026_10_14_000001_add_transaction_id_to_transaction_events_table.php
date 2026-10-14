<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->string('transaction_id', 30)->nullable()->after('id');
            $table->index('transaction_id', 'transaction_events_transaction_id_index');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE transaction_events
                INNER JOIN transaction_history
                    ON transaction_history.id = transaction_events.transferred_transaction_id
                SET transaction_events.transaction_id = transaction_history.transaction_id
                WHERE transaction_events.transferred_transaction_id IS NOT NULL
            SQL);
        } else {
            DB::table('transaction_events')
                ->whereNotNull('transferred_transaction_id')
                ->update([
                    'transaction_id' => DB::raw(
                        '(SELECT transaction_id FROM transaction_history'
                        .' WHERE transaction_history.id = transaction_events.transferred_transaction_id)'
                    ),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->dropIndex('transaction_events_transaction_id_index');
            $table->dropColumn('transaction_id');
        });
    }
};
