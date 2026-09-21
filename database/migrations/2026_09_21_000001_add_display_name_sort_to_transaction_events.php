<?php

use App\Support\ImportName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->string('display_name_sort', 191)->nullable()->after('full_name');
        });

        DB::table('transaction_events')
            ->select('id', 'full_name')
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                $values = $events->map(fn ($event) => [
                    'id' => $event->id,
                    'full_name' => $event->full_name,
                    'display_name_sort' => mb_strtolower(ImportName::format((string) $event->full_name)),
                ])->all();

                DB::table('transaction_events')->upsert($values, ['id'], ['display_name_sort']);
            });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->dropColumn('display_name_sort');
        });
    }
};
