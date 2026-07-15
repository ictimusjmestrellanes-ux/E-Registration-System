<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_history', 'subject_first_name')) {
                $table->string('subject_first_name')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_middle_name')) {
                $table->string('subject_middle_name')->nullable()->after('subject_first_name');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_last_name')) {
                $table->string('subject_last_name')->nullable()->after('subject_middle_name');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_name_ext')) {
                $table->string('subject_name_ext', 20)->nullable()->after('subject_last_name');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_gender')) {
                $table->string('subject_gender', 20)->nullable()->after('subject_name_ext');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_birthdate')) {
                $table->date('subject_birthdate')->nullable()->after('subject_gender');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_age')) {
                $table->unsignedSmallInteger('subject_age')->nullable()->after('subject_birthdate');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_barangay')) {
                $table->string('subject_barangay')->nullable()->after('subject_age');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_municipality')) {
                $table->string('subject_municipality')->nullable()->after('subject_barangay');
            }
            if (!Schema::hasColumn('transaction_history', 'subject_client_relation')) {
                $table->string('subject_client_relation', 100)->nullable()->after('subject_municipality');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $columns = [
                'subject_first_name',
                'subject_middle_name',
                'subject_last_name',
                'subject_name_ext',
                'subject_gender',
                'subject_birthdate',
                'subject_age',
                'subject_barangay',
                'subject_municipality',
                'subject_client_relation',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('transaction_history', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
