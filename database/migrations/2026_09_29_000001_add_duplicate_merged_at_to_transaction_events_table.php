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
            $table->timestamp('duplicate_merged_at')->nullable()->after('not_duplicate');
        });

        // Merges performed before this column existed were represented as
        // "not duplicate" reviews. Recover their event IDs from the merge
        // audit so deployed databases immediately reflect the distinct state.
        DB::table('activity_logs')
            ->where('action', 'duplicate_event_clients_merged')
            ->orderBy('id')
            ->each(function ($log): void {
                $properties = is_string($log->properties)
                    ? json_decode($log->properties, true)
                    : (array) $log->properties;
                $eventIds = array_values(array_filter(
                    array_map('intval', (array) ($properties['event_ids'] ?? [])),
                    fn (int $id): bool => $id > 0
                ));

                if ($eventIds !== []) {
                    DB::table('transaction_events')->whereIn('id', $eventIds)->update([
                        'not_duplicate' => false,
                        'duplicate_merged_at' => $log->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('transaction_events')->whereNotNull('duplicate_merged_at')->update([
            'not_duplicate' => true,
        ]);

        Schema::table('transaction_events', function (Blueprint $table) {
            $table->dropColumn('duplicate_merged_at');
        });
    }
};
