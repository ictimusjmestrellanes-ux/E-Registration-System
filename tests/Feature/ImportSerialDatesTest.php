<?php

namespace Tests\Feature;

use App\Http\Controllers\TransactionEventsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportSerialDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_serial_dates_are_normalized_not_skipped(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        // 46268 is how Excel stores a Sep 2026 date in a date-formatted cell
        // when read as a raw value by the native .xlsx parser.
        $csv = "full_name,contact_no,address,age,birth_date,client_category,transaction_category,transaction_type,event_date\n"
            . "Serial Date User,09170000001,Brgy 1,,1990-01-01,INDIGENT,BIGAY BIGAS SA MASA,TRANCH 1,46268\n";

        $file = UploadedFile::fake()->createWithContent('serial-dates.csv', $csv);

        $response = $this->postJson(route('transaction-events.import.prepare'), [
            'csv_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('skipped', 0);

        $rows = $response->json('preview_rows');
        $this->assertNotEmpty($rows);
        // Serial converted to a real calendar date (Sep 2026).
        $this->assertMatchesRegularExpression('/^2026-09-\d{2}$/', $rows[0]['event_date']);
    }

    public function test_non_serial_values_pass_through_untouched(): void
    {
        $controller = new TransactionEventsController();
        $ref = new \ReflectionMethod($controller, 'normalizeMaybeExcelSerialDate');

        // Already-valid date strings are untouched.
        $this->assertSame('2026-03-09', $ref->invoke($controller, '2026-03-09'));
        // Small numbers (e.g. an age typed in a date column) must still fail
        // validation downstream, not become silent 1900s dates.
        $this->assertSame('25', $ref->invoke($controller, '25'));
        $this->assertSame('', $ref->invoke($controller, ''));
        // A 1941+ serial converts.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $ref->invoke($controller, '30000')
        );
    }
}
