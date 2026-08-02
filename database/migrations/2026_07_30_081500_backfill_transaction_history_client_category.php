<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn('transaction_history', 'client_category')
            || !Schema::hasColumn('transaction_history', 'client_id')
            || !Schema::hasColumn('clients', 'sector')
        ) {
            return;
        }

        $categoriesByClientId = DB::table('clients')
            ->whereNotNull('client_id')
            ->whereNotNull('sector')
            ->where('sector', '<>', '')
            ->pluck('sector', 'client_id');

        DB::table('transaction_history')
            ->select('id', 'client_id')
            ->whereNotNull('client_id')
            ->where(function ($query) {
                $query->whereNull('client_category')
                    ->orWhere('client_category', '');
            })
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
        //
    }
};
