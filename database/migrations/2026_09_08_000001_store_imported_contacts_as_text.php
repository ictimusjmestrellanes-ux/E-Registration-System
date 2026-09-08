<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clients' => 'contact', 'archived_clients' => 'contact', 'transaction_events' => 'contact_no', 'transaction_event_archives' => 'contact_no'] as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->text($column)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['clients' => ['contact', 255], 'archived_clients' => ['contact', 30], 'transaction_events' => ['contact_no', 255], 'transaction_event_archives' => ['contact_no', 255]] as $tableName => [$column, $length]) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $length) {
                $table->string($column, $length)->nullable()->change();
            });
        }
    }
};
