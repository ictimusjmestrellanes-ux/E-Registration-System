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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('contact_2', 30)->nullable()->after('contact');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->string('contact_2', 30)->nullable()->after('contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('contact_2');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->dropColumn('contact_2');
        });
    }
};
