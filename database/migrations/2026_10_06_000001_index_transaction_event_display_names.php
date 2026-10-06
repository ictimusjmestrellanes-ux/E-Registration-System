<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->index('display_name_sort', 'transaction_events_display_name_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->dropIndex('transaction_events_display_name_sort_index');
        });
    }
};
