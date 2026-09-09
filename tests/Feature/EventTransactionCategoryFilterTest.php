<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTransactionCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filters_match_lists_exports_and_select_all_for_both_pages(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        foreach ([false, true] as $transferred) {
            $records = collect(['Food', 'Medical', 'Education'])->map(fn ($category) => TransactionEvent::create([
                'full_name' => ($transferred ? 'Transferred ' : 'Pending ').$category,
                'transaction_category' => $category,
                'transaction_type' => 'Assistance',
                'transferred_at' => $transferred ? now() : null,
            ]));
            $list = $transferred ? 'transaction-events.records' : 'transaction-events.index';
            $exportRoute = $transferred ? 'transaction-events.records.export' : 'transaction-events.export';
            $idsRoute = $transferred ? 'transaction-events.undo-transfer-selected.ids' : 'transaction-events.transfer-selected.ids';

            foreach ([['Food', 'Medical'], 'Medical', ['']] as $selection) {
                $filters = ['transaction_category' => $selection];
                $expected = $selection === [''] ? $records : $records->whereIn('transaction_category', (array) $selection);
                $response = $this->get(route($list, $filters));
                $response->assertOk()->assertSee('name="transaction_category[]"', false);
                $response->assertViewHas('events', fn ($events) => $events->pluck('id')->sort()->values()->all()
                    === $expected->pluck('id')->sort()->values()->all());

                $ids = $this->postJson(route($idsRoute), $filters + ['select_all' => 1]);
                $ids->assertOk()->assertJsonPath('total', $expected->count());
                $this->assertEqualsCanonicalizing($expected->pluck('id')->all(), $ids->json('ids'));

                $export = $this->get(route($exportRoute, $filters));
                $export->assertOk();
                $zip = new \ZipArchive();
                $this->assertTrue($zip->open($export->getFile()->getPathname()));
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                foreach ($records as $record) {
                    if ($expected->contains('id', $record->id)) {
                        $this->assertStringContainsString($record->full_name, $sheet);
                    } else {
                        $this->assertStringNotContainsString($record->full_name, $sheet);
                    }
                }
                $zip->close();
            }
        }
    }
}
