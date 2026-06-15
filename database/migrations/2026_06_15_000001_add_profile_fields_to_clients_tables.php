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
            $table->string('suffix', 20)->nullable()->after('last_name');
            $table->date('birth_date')->nullable()->after('age');
            $table->string('birthplace')->nullable()->after('birth_date');
            $table->string('education')->nullable()->after('birthplace');
            $table->string('course')->nullable()->after('education');
            $table->string('sector')->nullable()->after('course');
            $table->string('position_organization')->nullable()->after('sector');
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->string('suffix', 20)->nullable()->after('last_name');
            $table->date('birth_date')->nullable()->after('age');
            $table->string('birthplace')->nullable()->after('birth_date');
            $table->string('education')->nullable()->after('birthplace');
            $table->string('course')->nullable()->after('education');
            $table->string('sector')->nullable()->after('course');
            $table->string('position_organization')->nullable()->after('sector');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'suffix',
                'birth_date',
                'birthplace',
                'education',
                'course',
                'sector',
                'position_organization',
            ]);
        });

        Schema::table('archived_clients', function (Blueprint $table) {
            $table->dropColumn([
                'suffix',
                'birth_date',
                'birthplace',
                'education',
                'course',
                'sector',
                'position_organization',
            ]);
        });
    }
};
