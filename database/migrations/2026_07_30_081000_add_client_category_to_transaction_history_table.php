<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_history', 'client_category')) {
                $table->string('client_category', 100)->nullable()->after('client_id');
            }
        });

        if (!Schema::hasColumn('clients', 'sector')) {
            return;
        }

        $categoriesByClientId = DB::table('clients')
            ->whereNotNull('client_id')
            ->whereNotNull('sector')
            ->pluck('sector', 'client_id');

        DB::table('transaction_history')
            ->select('id', 'client_id')
            ->whereNull('client_category')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use ($categoriesByClientId) {
                foreach ($transactions as $transaction) {
                    $clientCategory = $categoriesByClientId[$transaction->client_id] ?? null;

                    if ($clientCategory) {
                        DB::table('transaction_history')
                            ->where('id', $transaction->id)
                            ->update(['client_category' => $clientCategory]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_history', 'client_category')) {
                $table->dropColumn('client_category');
            }
        });
    }
};
