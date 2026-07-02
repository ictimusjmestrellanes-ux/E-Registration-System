<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('sector', 500)->nullable()->change();
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->string('sector', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('sector', 255)->nullable()->change();
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->string('sector', 255)->nullable()->change();
        });
    }
};
