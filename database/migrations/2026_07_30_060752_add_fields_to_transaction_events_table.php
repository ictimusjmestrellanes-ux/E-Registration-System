<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('age');
            $table->string('client_category', 100)->nullable()->after('birth_date');
            $table->string('transaction_category', 100)->nullable()->after('client_category');
            $table->string('transaction_type', 100)->nullable()->after('transaction_category');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'client_category', 'transaction_category', 'transaction_type']);
        });
    }
};
