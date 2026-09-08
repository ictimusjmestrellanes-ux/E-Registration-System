<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardDataRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_totals_and_chart_payloads_refresh_after_records_are_created(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Cache::flush();

        // Prime every dashboard cache with an empty database first. The
        // records below must be visible without waiting for the cache TTL.
        $initialDashboard = $this->get(route('dashboard'));
        $initialDashboard->assertOk();
        $this->assertSame(0, $initialDashboard->viewData('totalClients'));
        $this->assertSame(0, $initialDashboard->viewData('totalTransactions'));
        $this->assertSame(['labels' => ['Aug 2026', 'Sep 2026'], 'data' => [0, 0]], $initialDashboard->viewData('transactionDateTrend'));

        $registeredAt = Carbon::parse('2026-08-15 10:00:00');
        $client = Client::forceCreate([
            'client_id' => '2500001',
            'first_name' => 'Dashboard',
            'last_name' => 'Client',
            'sector' => 'INDIGENT',
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
        ]);

        TransactionHistory::forceCreate([
            'transaction_id' => '2500001-25-0001',
            'client_id' => $client->client_id,
            'client_category' => 'INDIGENT',
            'transaction_date' => '2026-08-15',
            'category' => 'CARAVAN',
            'type' => 'CARAVAN',
            'events_transaction_type' => 'TRANCH 1',
            'status' => 'Approved',
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
        ]);

        $dashboard = $this->get(route('dashboard'));
        $dashboard->assertOk();

        $this->assertSame(1, $dashboard->viewData('totalClients'));
        $this->assertSame(1, $dashboard->viewData('totalTransactions'));
        $this->assertSame(1, $dashboard->viewData('categoryCounts')['events']);
        $this->assertSame(['CARAVAN'], $dashboard->viewData('txCategoryOptions'));
        $this->assertSame(['TRANCH 1'], $dashboard->viewData('txTypeOptions'));
        $this->assertSame(
            ['labels' => ['INDIGENT'], 'data' => [1]],
            $dashboard->viewData('clientCategoryDistribution')
        );

        $this->assertSame(['Aug 2026', 'Sep 2026'], $dashboard->viewData('clientTrend')['labels']);
        $this->assertMonthlyPoint($dashboard->viewData('clientTrend'), 'Aug 2026', 1);
        $this->assertMonthlyPoint($dashboard->viewData('transactionDateTrend'), 'Aug 2026', 1);

        $transactionTrend = $this->getJson(route('dashboard.transaction-trend', [
            'tx_category' => ['CARAVAN'],
            'tx_type' => ['TRANCH 1'],
        ]));
        $transactionTrend
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('categories.0', 'CARAVAN')
            ->assertJsonPath('types.0', 'TRANCH 1');

        $this->assertSame(['Aug 2026', 'Sep 2026'], $transactionTrend->json('labels'));
        $this->assertTransactionTrendPoint($transactionTrend->json(), 'Aug 2026', 1);
    }

    public function test_charts_show_all_previous_months_through_current_month_across_year_boundary(): void
    {
        Carbon::setTestNow('2026-01-31 12:00:00');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Cache::flush();

        $emptyDashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(
            ['labels' => ['Dec 2025', 'Jan 2026'], 'data' => [0, 0]],
            $emptyDashboard->viewData('clientTrend')
        );
        $this->assertSame(['Dec 2025', 'Jan 2026'], $emptyDashboard->viewData('transactionTrend')['labels']);

        foreach (['2025-10-31', '2025-12-01', '2026-01-31', '2026-02-01'] as $index => $date) {
            $client = Client::forceCreate([
                'client_id' => '260000'.($index + 1),
                'first_name' => 'Monthly',
                'last_name' => 'Client',
                'created_at' => $date.' 10:00:00',
                'updated_at' => $date.' 10:00:00',
            ]);
            TransactionHistory::forceCreate([
                'transaction_id' => $client->client_id.'-26-0001',
                'client_id' => $client->client_id,
                'transaction_date' => $date,
                'category' => 'CARAVAN',
                'type' => 'CARAVAN',
                'events_transaction_type' => 'TRANCH 1',
                'status' => 'Approved',
            ]);
        }

        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(4, $dashboard->viewData('totalClients'));
        $this->assertSame(4, $dashboard->viewData('totalTransactions'));
        $this->assertSame(
            ['labels' => ['Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026'], 'data' => [1, 0, 1, 1]],
            $dashboard->viewData('clientTrend')
        );
        $this->assertSame(['Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026'], $dashboard->viewData('transactionTrend')['labels']);
        $this->assertTransactionTrendPoint($dashboard->viewData('transactionTrend'), 'Oct 2025', 1);
        $this->assertTransactionTrendPoint($dashboard->viewData('transactionTrend'), 'Nov 2025', 0);
        $this->assertTransactionTrendPoint($dashboard->viewData('transactionTrend'), 'Dec 2025', 1);
        $this->assertTransactionTrendPoint($dashboard->viewData('transactionTrend'), 'Jan 2026', 1);

        $filtered = $this->getJson(route('dashboard.transaction-trend', [
            'tx_category' => ['CARAVAN'],
            'tx_type' => ['TRANCH 1'],
        ]))->assertOk();
        $this->assertSame(['Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026'], $filtered->json('labels'));
        $this->assertTransactionTrendPoint($filtered->json(), 'Oct 2025', 1);
        $this->assertTransactionTrendPoint($filtered->json(), 'Nov 2025', 0);
        $this->assertTransactionTrendPoint($filtered->json(), 'Dec 2025', 1);
        $this->assertTransactionTrendPoint($filtered->json(), 'Jan 2026', 1);
    }

    public function test_monthly_columns_use_transaction_dates_and_combine_all_categories(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));
        Cache::flush();
        $client = Client::forceCreate([
            'client_id' => '2600001',
            'first_name' => 'Monthly',
            'last_name' => 'Columns',
        ]);

        foreach ([
            ['2026-06-01', 'CARAVAN', 'TRANCH 1'],
            ['2026-06-30', '', ''],
            ['2026-08-15', 'social_services', 'medical_assistance'],
            ['2026-10-01', 'CARAVAN', 'TRANCH 1'],
        ] as $index => [$date, $category, $type]) {
            TransactionHistory::forceCreate([
                'client_id' => $client->client_id,
                'transaction_id' => '2600001-26-000'.($index + 1),
                'transaction_date' => $date,
                'category' => $category,
                'type' => $type,
                'events_transaction_type' => $index === 0 ? 'EVENT TYPE' : null,
                'status' => 'Approved',
                // All rows are imported in September, regardless of transaction month.
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $dashboard = $this->get(route('dashboard', ['tx_category' => ['CARAVAN']]))->assertOk();
        $dashboard->assertSee('Transactions by Transaction Date');
        $dashboard->assertSee('id="transactionDateChart"', false);
        $this->assertSame([
            'labels' => ['Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026'],
            'data' => [2, 0, 1, 0],
        ], $dashboard->viewData('transactionDateTrend'));

        foreach ([
            ['2026-06-30', '2026-08-14', ['Jun 2026', 'Jul 2026', 'Aug 2026'], [1, 0, 0]],
            ['2026-06-30', '2026-06-30', ['Jun 2026'], [1]],
            [null, '2026-06-01', ['Jun 2026'], [1]],
            ['2026-08-15', null, ['Aug 2026', 'Sep 2026'], [1, 0]],
            ['2025-11-01', '2025-12-31', ['Nov 2025', 'Dec 2025'], [0, 0]],
        ] as [$from, $to, $labels, $counts]) {
            $filtered = $this->get(route('dashboard', [
                'transaction_date_from' => $from,
                'transaction_date_to' => $to,
                'tx_category' => ['CARAVAN'],
            ]))->assertOk();
            $this->assertSame(['labels' => $labels, 'data' => $counts], $filtered->viewData('transactionDateTrend'));
            $this->assertSame(4, $filtered->viewData('totalTransactions'));
            $this->assertSame($dashboard->viewData('transactionTrend'), $filtered->viewData('transactionTrend'));
        }

        $reset = $this->get(route('dashboard'))->assertOk();
        $this->assertSame($dashboard->viewData('transactionDateTrend'), $reset->viewData('transactionDateTrend'));

        $this->assertSame(['2026-10-01', '2026-08-15', '2026-06-30', '2026-06-01'], $reset->viewData('transactionDateOptions'));
        $reset->assertSee('name="transaction_dates[]"', false)->assertSee('Jun 30, 2026');
        $selected = $this->get(route('dashboard', [
            'transaction_dates' => ['2026-08-15', '2026-06-01', '2026-06-01'],
        ]))->assertOk();
        $this->assertSame(['2026-06-01', '2026-08-15'], $selected->viewData('transactionDates'));
        $this->assertSame([
            'labels' => ['Jun 2026', 'Jul 2026', 'Aug 2026'],
            'data' => [1, 0, 1],
        ], $selected->viewData('transactionDateTrend'));
        $singleDate = $this->get(route('dashboard', ['transaction_dates' => ['2026-06-30']]))->assertOk();
        $this->assertSame(['labels' => ['Jun 2026'], 'data' => [1]], $singleDate->viewData('transactionDateTrend'));

        $live = $this->getJson(route('dashboard.transaction-date-trend', [
            'transaction_dates' => ['2026-08-15', '2026-06-01', '2026-06-01'],
        ]))->assertOk()->assertJsonPath('total', 2);
        $this->assertSame($selected->viewData('transactionDateTrend')['labels'], $live->json('labels'));
        $this->assertSame($selected->viewData('transactionDateTrend')['data'], $live->json('data'));
        $this->assertSame(['2026-06-01', '2026-08-15'], $live->json('dates'));
        $allDates = $this->getJson(route('dashboard.transaction-date-trend'))->assertOk()->assertJsonPath('dates', []);
        $this->assertSame($reset->viewData('transactionDateTrend')['data'], $allDates->json('data'));
        $this->getJson(route('dashboard.transaction-date-trend', ['transaction_dates' => ['2026-07-01']]))
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_dates.0');

        foreach ([
            [[], ['CARAVAN'], [1, 0, 0, 0]],
            [[], ['CARAVAN', 'social_services'], [1, 0, 1, 0]],
            [['2026-06-01', '2026-08-15'], ['social_services'], [1]],
            [['2026-06-30'], ['CARAVAN'], [1, 0, 0, 0]],
        ] as [$dates, $categories, $counts]) {
            $filters = ['transaction_dates' => $dates, 'transaction_date_categories' => $categories];
            $filteredLive = $this->getJson(route('dashboard.transaction-date-trend', $filters))
                ->assertOk()->assertJsonPath('categories', $categories)->assertJsonPath('total', array_sum($counts));
            $this->assertSame($counts, $filteredLive->json('data'));
            $filteredDashboard = $this->get(route('dashboard', $filters))->assertOk();
            $this->assertSame($counts, $filteredDashboard->viewData('transactionDateTrend')['data']);
            $this->assertSame($categories, $filteredDashboard->viewData('transactionDateCategories'));
            $this->assertSame(4, $filteredDashboard->viewData('totalTransactions'));
        }
        $this->getJson(route('dashboard.transaction-date-trend', ['transaction_date_categories' => ['unknown']]))
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_date_categories.0');

        foreach ([
            [[], [], ['EVENT TYPE'], [1, 0, 0, 0]],
            [[], [], ['TRANCH 1'], [0, 0, 0, 0]],
            [[], [], ['medical_assistance'], [0, 0, 1, 0]],
            [['2026-06-01', '2026-08-15'], [], ['EVENT TYPE', 'medical_assistance'], [1, 0, 1]],
            [['2026-06-01', '2026-08-15'], ['social_services'], ['medical_assistance'], [1]],
            [['2026-06-01'], ['social_services'], ['medical_assistance'], [0, 0, 1, 0]],
        ] as [$dates, $categories, $types, $counts]) {
            $filters = ['transaction_dates' => $dates, 'transaction_date_categories' => $categories, 'transaction_date_types' => $types];
            $live = $this->getJson(route('dashboard.transaction-date-trend', $filters))
                ->assertOk()->assertJsonPath('types', $types)->assertJsonPath('total', array_sum($counts));
            $this->assertSame($counts, $live->json('data'));
            $page = $this->get(route('dashboard', $filters))->assertOk();
            $this->assertSame($types, $page->viewData('transactionDateTypes'));
            $this->assertSame($counts, $page->viewData('transactionDateTrend')['data']);
        }
        $this->getJson(route('dashboard.transaction-date-trend', ['transaction_date_types' => ['unknown']]))
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_date_types.0');

        $this->getJson(route('dashboard.transaction-date-trend', ['transaction_date_categories' => ['CARAVAN']]))
            ->assertOk()
            ->assertJsonPath('type_options', ['EVENT TYPE', 'TRANCH 1'])
            ->assertJsonPath('date_options', ['2026-10-01', '2026-06-01']);
        $this->getJson(route('dashboard.transaction-date-trend', [
            'transaction_date_categories' => ['CARAVAN'], 'transaction_date_types' => ['EVENT TYPE'],
        ]))->assertOk()->assertJsonPath('date_options', ['2026-06-01']);

        // Changing category clears the previous category's selected type/date.
        $changedFilters = [
            'transaction_date_categories' => ['social_services'],
            'transaction_date_types' => ['EVENT TYPE'],
            'transaction_dates' => ['2026-06-01'],
        ];
        $changed = $this->getJson(route('dashboard.transaction-date-trend', $changedFilters))
            ->assertOk()->assertJsonPath('types', [])->assertJsonPath('dates', [])
            ->assertJsonPath('type_options', ['medical_assistance'])
            ->assertJsonPath('date_options', ['2026-08-15'])->assertJsonPath('total', 1);
        $changedPage = $this->get(route('dashboard', $changedFilters))->assertOk();
        $this->assertSame([], $changedPage->viewData('transactionDateTypes'));
        $this->assertSame([], $changedPage->viewData('transactionDates'));
        $this->assertSame($changed->json('type_options'), $changedPage->viewData('transactionDateTypeOptions'));
        $this->assertSame($changed->json('date_options'), $changedPage->viewData('transactionDateVisibleDates'));
        $this->getJson(route('dashboard.transaction-date-trend', [
            'transaction_date_categories' => ['CARAVAN', 'social_services'],
            'transaction_date_types' => ['EVENT TYPE', 'medical_assistance'],
        ]))->assertOk()->assertJsonPath('type_options', ['EVENT TYPE', 'TRANCH 1', 'medical_assistance'])
            ->assertJsonPath('date_options', ['2026-08-15', '2026-06-01']);
    }

    public function test_transaction_date_filters_reject_invalid_ranges(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        foreach ([
            [['transaction_date_from' => 'invalid'], 'transaction_date_from'],
            [['transaction_date_to' => '2026-02-30'], 'transaction_date_to'],
            [['transaction_date_from' => '2026-08-15', 'transaction_date_to' => '2026-08-14'], 'transaction_date_to'],
            [['transaction_date_from' => '2026-09-09'], 'transaction_date_from'],
            [['transaction_date_to' => '2026-09-09'], 'transaction_date_to'],
            [['transaction_dates' => '2026-06-01'], 'transaction_dates'],
            [['transaction_dates' => ['invalid']], 'transaction_dates.0'],
            [['transaction_dates' => ['2026-06-01']], 'transaction_dates.0'],
        ] as [$filters, $error]) {
            $this->from(route('dashboard'))->get(route('dashboard', $filters))
                ->assertRedirect(route('dashboard'))
                ->assertSessionHasErrors($error);
        }
    }

    public function test_total_transactions_can_filter_types_across_all_categories_and_reset(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        foreach ([['CARAVAN', 'TRANCH 1'], ['events', 'TRANCH 1'], ['events', 'TRANCH 2']] as $index => [$category, $type]) {
            TransactionHistory::forceCreate([
                'transaction_id' => 'TYPE-FILTER-'.$index,
                'client_id' => '2600001',
                'category' => $category,
                'type' => 'EVENTS',
                'events_transaction_type' => $type,
                'transaction_date' => '2026-09-08',
            ]);
        }

        $filters = ['tx_type' => ['TRANCH 1']];
        $filtered = $this->getJson(route('dashboard.transaction-trend', $filters))
            ->assertOk()->assertJsonPath('types', ['TRANCH 1']);
        $this->assertTransactionTrendPoint($filtered->json(), 'Sep 2026', 2);

        $dashboard = $this->get(route('dashboard', $filters))->assertOk();
        $this->assertSame(['TRANCH 1'], $dashboard->viewData('txTypes'));
        $this->assertTransactionTrendPoint($dashboard->viewData('transactionTrend'), 'Sep 2026', 2);

        $reset = $this->getJson(route('dashboard.transaction-trend'))
            ->assertOk()->assertJsonPath('types', []);
        $this->assertTransactionTrendPoint($reset->json(), 'Sep 2026', 3);

        $this->getJson(route('dashboard.transaction-trend.types'))
            ->assertOk()->assertJsonPath('types', ['TRANCH 1', 'TRANCH 2']);
    }

    /**
     * @param  array{labels: array<int, string>, data: array<int, int|string>}  $chart
     */
    private function assertMonthlyPoint(array $chart, string $label, int $expected): void
    {
        $index = array_search($label, $chart['labels'], true);

        $this->assertNotFalse($index, "Monthly chart does not contain {$label}.");
        $this->assertSame($expected, (int) ($chart['data'][$index] ?? 0));
    }

    /**
     * @param  array{labels: array<int, string>, datasets: array<int, array{data: array<int, int|string>}>}  $payload
     */
    private function assertTransactionTrendPoint(array $payload, string $label, int $expected): void
    {
        $index = array_search($label, $payload['labels'], true);

        $this->assertNotFalse($index, "Transaction trend does not contain {$label}.");
        $actual = array_sum(array_map(
            fn (array $dataset) => (int) ($dataset['data'][$index] ?? 0),
            $payload['datasets']
        ));

        $this->assertSame($expected, $actual);
    }
}
