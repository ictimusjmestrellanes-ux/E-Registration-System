<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_id', 20)->nullable()->unique()->after('id');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->string('client_id', 20)->nullable()->after('original_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }
};
