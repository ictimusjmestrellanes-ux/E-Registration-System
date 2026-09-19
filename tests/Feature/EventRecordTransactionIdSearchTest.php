<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRecordTransactionIdSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_accepts_either_transaction_id_segment_or_the_full_id(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $first = $this->transferredEvent('2600201-26-0001', 'First Record');
        $second = $this->transferredEvent('2600201-26-0002', 'Second Record');
        $other = $this->transferredEvent('2600001-26-0002', 'Other Record');

        foreach ([
            '2600201' => [$first->id, $second->id],
            '0001' => [$first->id],
            '2600201-26-0001' => [$first->id],
            "('2600201')" => [$first->id, $second->id],
            "('2600201')-26-('0001')" => [$first->id],
            'First Record' => [$first->id],
        ] as $search => $expectedIds) {
            $this->get(route('transaction-events.records', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('events', function ($events) use ($expectedIds, $other) {
                    $ids = $events->pluck('id')->sort()->values()->all();
                    sort($expectedIds);

                    return $ids === $expectedIds && ! in_array($other->id, $ids, true);
                });
        }

        $this->postJson(route('transaction-events.undo-transfer-selected.ids'), [
            'select_all' => 1,
            'search' => '0001',
        ])->assertOk()->assertJsonPath('ids.0', $first->id)->assertJsonPath('total', 1);
    }

    private function transferredEvent(string $transactionId, string $name): TransactionEvent
    {
        $history = TransactionHistory::create([
            'client_id' => explode('-', $transactionId, 2)[0],
            'transaction_id' => $transactionId,
            'transaction_date' => '2026-09-18',
            'category' => 'others',
            'type' => 'test',
        ]);

        return TransactionEvent::create([
            'full_name' => $name,
            'transferred_at' => now(),
            'transferred_transaction_id' => $history->id,
        ]);
    }
}
