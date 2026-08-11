<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->string('signatory', 100)->nullable()->after('clerk');
            $table->string('personnel_endorsed_to', 100)->nullable()->after('signatory');
            $table->string('responsible_office', 100)->nullable()->after('personnel_endorsed_to');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->dropColumn(['signatory', 'personnel_endorsed_to', 'responsible_office']);
        });
    }
};
