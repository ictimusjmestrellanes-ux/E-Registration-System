<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->string('payroll_category', 100)->nullable()->after('client_category');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_events', function (Blueprint $table): void {
            $table->dropColumn('payroll_category');
        });
    }
};
