<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Support\PayrollClaimedTranche;
use Illuminate\Support\Collection;

class EventRecordsPayrollRows
{
    /**
     * Collapse transferred Event Records into one printable row per client ID.
     * Records without a linked client ID remain independent rows.
     */
    public function merge(Collection $events): Collection
    {
        $clientIds = TransactionHistory::whereIn(
            'id',
            $events->pluck('transferred_transaction_id')->filter()->unique()
        )->pluck('client_id', 'id');
        $clients = Client::whereIn('client_id', $clientIds->values()->filter()->unique())
            ->get(['client_id', 'gender'])
            ->keyBy('client_id');

        $rows = collect();
        $rowIndexes = [];

        foreach ($events as $event) {
            $clientId = trim((string) $clientIds->get($event->transferred_transaction_id));
            $event->setAttribute('export_sex', $clients->get($clientId)?->gender ?? '');
            $event->setAttribute('payroll_client_id', $clientId);

            $claimedTranche = PayrollClaimedTranche::fromTransactionType($event->transaction_type);
            $event->setAttribute('payroll_claimed_tranches', $claimedTranche === null ? [] : [$claimedTranche]);

            if ($clientId === '') {
                $rows->push($event);

                continue;
            }

            $key = mb_strtoupper($clientId);
            if (! array_key_exists($key, $rowIndexes)) {
                $rowIndexes[$key] = $rows->count();
                $rows->push($event);

                continue;
            }

            $row = $rows->get($rowIndexes[$key]);
            $claimedTranches = array_values(array_unique(array_merge(
                (array) $row->getAttribute('payroll_claimed_tranches'),
                $claimedTranche === null ? [] : [$claimedTranche]
            )));
            sort($claimedTranches);
            $row->setAttribute('payroll_claimed_tranches', $claimedTranches);

            // Keep the first alphabetically sorted snapshot, filling only values
            // that are absent there from another record belonging to the client.
            foreach (['full_name', 'address', 'age', 'client_category', 'payroll_category', 'transaction_category'] as $attribute) {
                if (blank($row->getAttribute($attribute)) && filled($event->getAttribute($attribute))) {
                    $row->setAttribute($attribute, $event->getAttribute($attribute));
                }
            }
        }

        return $rows->values();
    }
}
