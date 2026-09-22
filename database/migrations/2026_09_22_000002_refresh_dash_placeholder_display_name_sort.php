<?php

use App\Support\ImportName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transaction_events', 'display_name_sort')) {
            return;
        }

        DB::table('transaction_events')
            ->select('id', 'full_name')
            ->where('full_name', 'like', '%--%')
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                $values = $events->map(fn ($event) => [
                    'id' => $event->id,
                    'full_name' => $event->full_name,
                    'display_name_sort' => mb_strtolower(ImportName::format((string) $event->full_name)),
                ])->all();

                if ($values !== []) {
                    DB::table('transaction_events')->upsert($values, ['id'], ['display_name_sort']);
                }
            });
    }

    public function down(): void
    {
        // display_name_sort is derived data; there is no earlier value to restore.
    }
};
