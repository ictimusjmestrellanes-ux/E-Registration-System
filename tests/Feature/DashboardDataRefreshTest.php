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
