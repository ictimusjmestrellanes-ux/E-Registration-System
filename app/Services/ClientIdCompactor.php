<?php

namespace App\Services;

use App\Models\ArchivedClient;
use App\Models\Client;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClientIdCompactor
{
    /**
     * Close gaps left by deleted clients. Call this inside the same database
     * transaction as the deletion so IDs and their history change together.
     *
     * @param  string[]  $deletedClientIds
     */
    public function compactAfterDeletion(array $deletedClientIds, ?callable $reportProgress = null): int
    {
        $reportProgress?->__invoke('Finding available client IDs...', 0);
        $firstDeletedByYear = [];
        foreach ($deletedClientIds as $clientId) {
            if (!preg_match('/^(\d{2})(\d{5})$/', $clientId, $matches)) {
                continue;
            }
            $firstDeletedByYear[$matches[1]] = min(
                $firstDeletedByYear[$matches[1]] ?? PHP_INT_MAX,
                (int) $matches[2]
            );
        }

        if ($firstDeletedByYear === []) {
            return 0;
        }

        $changes = [];
        foreach ($firstDeletedByYear as $year => $firstDeleted) {
            $clients = Client::query()
                ->where('client_id', 'like', $year . '%')
                ->orderBy('client_id')
                ->lockForUpdate()
                ->get(['id', 'client_id']);
            $activeIds = array_fill_keys($clients->pluck('client_id')->filter()->all(), true);
            $reserved = array_fill_keys(
                ArchivedClient::query()->where('client_id', 'like', $year . '%')
                    ->pluck('client_id')->filter()->all(),
                true
            );

            // History without an active client still owns its old ID. Do not
            // assign that ID to another person, even if its client_id is null.
            DB::table('transaction_history')
                ->where('client_id', 'like', $year . '%')
                ->orWhere('transaction_id', 'like', $year . '%-%')
                ->select(['client_id', 'transaction_id'])
                ->orderBy('id')
                ->chunk(1000, function ($histories) use (&$reserved, $activeIds, $year) {
                    foreach ($histories as $history) {
                        $prefix = explode('-', (string) $history->transaction_id, 2)[0];
                        foreach ([(string) $history->client_id, $prefix] as $historyClientId) {
                            if (str_starts_with($historyClientId, $year)
                                && !isset($activeIds[$historyClientId])) {
                                $reserved[$historyClientId] = true;
                            }
                        }
                    }
                });

            $nextNumber = $firstDeleted;
            foreach ($clients as $client) {
                if (!preg_match('/^' . preg_quote($year, '/') . '(\d{5})$/', (string) $client->client_id, $matches)
                    || (int) $matches[1] < $firstDeleted) {
                    continue;
                }

                while ($nextNumber <= 99999 && isset($reserved[$year . sprintf('%05d', $nextNumber)])) {
                    $nextNumber++;
                }
                if ($nextNumber > 99999) {
                    throw new RuntimeException('No client IDs are available for renumbering.');
                }

                $oldNumber = (int) $matches[1];
                // Existing reserved IDs may force a gap; never move a client upward.
                if ($nextNumber > $oldNumber) {
                    $nextNumber = $oldNumber;
                }

                $newId = $year . sprintf('%05d', $nextNumber);
                if ($newId !== $client->client_id) {
                    $changes[] = [
                        'client_pk' => $client->id,
                        'old_id' => $client->client_id,
                        'new_id' => $newId,
                        'temp_id' => '~' . $client->id,
                    ];
                }
                $nextNumber++;
            }
        }

        if ($changes === []) {
            return 0;
        }

        $reportProgress?->__invoke('Updating client IDs...', count($changes));

        // Temporary IDs avoid unique-key collisions when 2600203 becomes
        // 2600202 while 2600202 (or another old ID) is still in the table.
        $map = 'client_id_renumber_' . bin2hex(random_bytes(6));
        $collation = DB::getDriverName() === 'mysql'
            ? ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            : '';
        DB::statement("CREATE TEMPORARY TABLE {$map} (client_pk BIGINT PRIMARY KEY, old_id VARCHAR(20) UNIQUE, new_id VARCHAR(20), temp_id VARCHAR(20)){$collation}");
        try {
            foreach (array_chunk($changes, 500) as $batch) {
                DB::table($map)->insert($batch);
            }

            $oldPrefix = "SUBSTR(transaction_history.transaction_id, 1, INSTR(transaction_history.transaction_id, '-') - 1)";
            $suffix = "SUBSTR(transaction_history.transaction_id, INSTR(transaction_history.transaction_id, '-'))";
            if (DB::getDriverName() === 'mysql') {
                DB::statement("UPDATE clients INNER JOIN {$map} ON {$map}.client_pk = clients.id SET clients.client_id = {$map}.temp_id");
                $reportProgress?->__invoke('Updating linked transaction history...', count($changes));
                DB::statement("UPDATE transaction_history INNER JOIN {$map} ON {$map}.old_id = transaction_history.client_id SET transaction_history.client_id = {$map}.new_id");
                DB::statement("UPDATE transaction_history INNER JOIN {$map} ON {$map}.old_id = {$oldPrefix} SET transaction_history.transaction_id = CONCAT({$map}.new_id, {$suffix})");
                $reportProgress?->__invoke('Finalizing client IDs...', count($changes));
                DB::statement("UPDATE clients INNER JOIN {$map} ON {$map}.client_pk = clients.id SET clients.client_id = {$map}.new_id");
            } else {
                DB::statement("UPDATE clients SET client_id = (SELECT temp_id FROM {$map} WHERE client_pk = clients.id) WHERE id IN (SELECT client_pk FROM {$map})");
                $reportProgress?->__invoke('Updating linked transaction history...', count($changes));
                DB::statement("UPDATE transaction_history SET client_id = (SELECT new_id FROM {$map} WHERE old_id = transaction_history.client_id) WHERE client_id IN (SELECT old_id FROM {$map})");
                DB::statement("UPDATE transaction_history SET transaction_id = (SELECT new_id || {$suffix} FROM {$map} WHERE old_id = {$oldPrefix}) WHERE {$oldPrefix} IN (SELECT old_id FROM {$map})");
                $reportProgress?->__invoke('Finalizing client IDs...', count($changes));
                DB::statement("UPDATE clients SET client_id = (SELECT new_id FROM {$map} WHERE client_pk = clients.id) WHERE id IN (SELECT client_pk FROM {$map})");
            }
        } finally {
            // MySQL's plain DROP TABLE commits the surrounding transaction,
            // even when the table being dropped is temporary.
            $drop = DB::getDriverName() === 'mysql' ? 'DROP TEMPORARY TABLE' : 'DROP TABLE';
            DB::statement("{$drop} IF EXISTS {$map}");
        }

        TransactionHistory::flushDashboardCache();

        return count($changes);
    }
}
