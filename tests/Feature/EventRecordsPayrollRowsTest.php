<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Services\EventRecordsPayrollRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventRecordsPayrollRowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_rows_merge_by_client_id_and_combine_claimed_tranches(): void
    {
        Client::create([
            'client_id' => 'C1',
            'first_name' => 'Ana',
            'last_name' => 'Able',
            'gender' => 'Female',
        ]);
        Client::create([
            'client_id' => 'C2',
            'first_name' => 'Ben',
            'last_name' => 'Baker',
            'gender' => 'Male',
        ]);

        $history = function (string $transactionId, string $clientId): int {
            return DB::table('transaction_history')->insertGetId([
                'transaction_id' => $transactionId,
                'client_id' => $clientId,
                'transaction_date' => '2026-10-23',
                'category' => 'BIGAY BIGAS SA MASA',
                'type' => 'BIGAY BIGAS SA MASA',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $events = collect([
            new TransactionEvent([
                'full_name' => 'ABLE, ANA',
                'transferred_transaction_id' => $history('C1-26-0001', 'C1'),
                'transaction_type' => 'SIR ALLEN',
            ]),
            new TransactionEvent([
                'full_name' => 'ABLE, ANA MARIE',
                'transferred_transaction_id' => $history('C1-26-0002', 'C1'),
                'transaction_type' => 'TRANCHE 2',
            ]),
            new TransactionEvent([
                'full_name' => 'BAKER, BEN',
                'transferred_transaction_id' => $history('C2-26-0001', 'C2'),
                'transaction_type' => 'TRANCHE 3',
            ]),
            new TransactionEvent(['full_name' => 'UNLINKED PERSON']),
            new TransactionEvent(['full_name' => 'UNLINKED PERSON']),
        ]);

        $rows = app(EventRecordsPayrollRows::class)->merge($events);

        $this->assertCount(4, $rows);
        $this->assertSame('C1', $rows[0]->payroll_client_id);
        $this->assertSame('ABLE, ANA', $rows[0]->full_name);
        $this->assertSame('FEMALE', mb_strtoupper($rows[0]->export_sex));
        $this->assertSame([1, 2], $rows[0]->payroll_claimed_tranches);
        $this->assertSame([3], $rows[1]->payroll_claimed_tranches);
        $this->assertSame('', $rows[2]->payroll_client_id);
        $this->assertSame('', $rows[3]->payroll_client_id);

        $html = view('pages.transaction_events.recordsPdf', [
            'events' => $rows,
            'isRice' => true,
            'dateLabel' => '',
            'details' => ['numbered_tranches' => [1, 2, 3, 4]],
        ])->render();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $cells = (new \DOMXPath($document))->query('//tbody/tr[1]/td');

        $this->assertSame('CLAIMED', trim($cells->item(8)->textContent));
        $this->assertSame('CLAIMED', trim($cells->item(9)->textContent));
        $this->assertSame('1', trim($cells->item(10)->textContent));
    }
}
