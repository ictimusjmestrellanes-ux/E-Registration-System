<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionEventTransactionIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_event_stores_and_synchronizes_the_display_transaction_id(): void
    {
        $client = Client::create([
            'client_id' => 'EVENT-TX-1',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);
        $history = TransactionHistory::create([
            'client_id' => $client->client_id,
            'transaction_id' => 'EVENT-TX-1-26-0001',
            'transaction_date' => '2026-10-14',
            'category' => 'Others',
            'type' => 'Test',
        ]);

        $event = TransactionEvent::create([
            'full_name' => $client->full_name,
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);

        $this->assertTrue(Schema::hasColumn('transaction_events', 'transaction_id'));
        $this->assertSame('EVENT-TX-1-26-0001', $event->transaction_id);

        $history->update(['transaction_id' => 'EVENT-TX-1-26-0002']);
        $this->assertSame('EVENT-TX-1-26-0002', $event->fresh()->transaction_id);

        $event->update([
            'transferred_at' => null,
            'transferred_transaction_id' => null,
        ]);
        $this->assertNull($event->fresh()->transaction_id);
    }
}
