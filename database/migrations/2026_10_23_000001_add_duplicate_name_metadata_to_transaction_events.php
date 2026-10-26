<?php

use App\Support\EventDuplicateName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->char('duplicate_person_key', 64)->nullable()->after('display_name_sort');
            $table->boolean('duplicate_middle_is_initial')->nullable()->after('duplicate_person_key');
            $table->boolean('duplicate_name_parsed')->default(false)->after('duplicate_middle_is_initial');
            $table->index(
                ['duplicate_person_key', 'duplicate_middle_is_initial'],
                'transaction_events_duplicate_person_initial_index'
            );
            $table->index('duplicate_name_parsed', 'transaction_events_duplicate_name_parsed_index');
        });

        DB::table('transaction_events')
            ->select('id', 'full_name')
            ->orderBy('id')
            ->chunkById(1000, function ($events): void {
                $values = $events->map(fn ($event) => [
                    'id' => $event->id,
                    'full_name' => $event->full_name,
                ]
                    + EventDuplicateName::attributes((string) $event->full_name))->all();

                DB::table('transaction_events')->upsert($values, ['id'], [
                    'duplicate_person_key',
                    'duplicate_middle_is_initial',
                    'duplicate_name_parsed',
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->dropIndex('transaction_events_duplicate_person_initial_index');
            $table->dropIndex('transaction_events_duplicate_name_parsed_index');
            $table->dropColumn([
                'duplicate_person_key',
                'duplicate_middle_is_initial',
                'duplicate_name_parsed',
            ]);
        });
    }
};
