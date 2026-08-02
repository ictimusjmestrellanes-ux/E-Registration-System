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
            if (!Schema::hasColumn('transaction_history', 'client_id')) {
                $table->string('client_id', 20)->nullable()->after('id')->index();
            }
        });

        $clients = DB::table('clients')
            ->select('client_id', 'first_name', 'last_name')
            ->whereNotNull('client_id')
            ->get();

        $clientsById = $clients->keyBy('client_id');
        $clientsByName = $clients->keyBy(function ($client) {
            return strtolower(trim($client->first_name . '|' . $client->last_name));
        });

        DB::table('transaction_history')
            ->select('id', 'transaction_id', 'description')
            ->whereNull('client_id')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use ($clientsById, $clientsByName) {
                foreach ($transactions as $transaction) {
                    $clientId = null;
                    $prefix = strtok((string) $transaction->transaction_id, '-');

                    if ($prefix && isset($clientsById[$prefix])) {
                        $clientId = $prefix;
                    }

                    if (!$clientId && preg_match('/Transferred from imported event for (.+)$/i', (string) $transaction->description, $matches)) {
                        $nameParts = $this->splitFullName($matches[1]);
                        $nameKey = strtolower(trim($nameParts['first_name'] . '|' . $nameParts['last_name']));
                        $clientId = $clientsByName[$nameKey]->client_id ?? null;
                    }

                    if ($clientId) {
                        DB::table('transaction_history')
                            ->where('id', $transaction->id)
                            ->update(['client_id' => $clientId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_history', 'client_id')) {
                $table->dropColumn('client_id');
            }
        });
    }

    private function splitFullName(string $fullName): array
    {
        $suffixes = ['jr', 'sr', 'ii', 'iii', 'iv', 'v'];
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        $count = count($parts);

        if ($count > 1 && in_array(strtolower(end($parts)), $suffixes)) {
            array_pop($parts);
            $count--;
        }

        if ($count === 1) {
            return ['first_name' => $parts[0], 'last_name' => ''];
        }

        if ($count === 2) {
            return ['first_name' => $parts[0], 'last_name' => $parts[1]];
        }

        return ['first_name' => array_shift($parts), 'last_name' => array_pop($parts)];
    }
};
