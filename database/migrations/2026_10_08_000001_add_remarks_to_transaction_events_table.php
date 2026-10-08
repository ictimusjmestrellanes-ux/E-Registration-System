<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('event_date');
        });

        DB::table('transaction_events')
            ->whereNotNull('transferred_transaction_id')
            ->whereNull('remarks')
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                $remarksByHistoryId = DB::table('transaction_history')
                    ->whereIn('id', $events->pluck('transferred_transaction_id')->filter()->unique())
                    ->pluck('remarks', 'id');

                foreach ($events as $event) {
                    $remarks = $remarksByHistoryId->get($event->transferred_transaction_id);
                    if ($remarks !== null) {
                        DB::table('transaction_events')
                            ->where('id', $event->id)
                            ->update(['remarks' => $remarks]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
