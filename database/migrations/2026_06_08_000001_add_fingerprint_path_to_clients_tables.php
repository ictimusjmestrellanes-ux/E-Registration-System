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
            $table->string('fingerprint_path')->nullable()->after('photo_path');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->string('fingerprint_path')->nullable()->after('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('fingerprint_path');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->dropColumn('fingerprint_path');
        });
    }
};
