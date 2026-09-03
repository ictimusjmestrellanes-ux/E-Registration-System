<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen contact_no columns so imported contacts with multiple
     * numbers (e.g. "09123456789/09123456789") are stored without
     * truncation.
     */
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->string('contact_no', 255)->nullable()->change();
        });

        Schema::table('transaction_event_archives', function (Blueprint $table) {
            $table->string('contact_no', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->string('contact_no', 30)->nullable()->change();
        });

        Schema::table('transaction_event_archives', function (Blueprint $table) {
            $table->string('contact_no', 30)->nullable()->change();
        });
    }
};
