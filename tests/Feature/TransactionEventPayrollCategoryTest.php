<?php

namespace Tests\Feature;

use App\Models\TransactionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionEventPayrollCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_category_is_set_and_resynchronized_from_client_category(): void
    {
        $event = TransactionEvent::create([
            'full_name' => 'Test Client',
            'client_category' => 'TODA',
        ]);

        $this->assertSame('TRANSPORT GROUP', $event->payroll_category);

        $event->update(['client_category' => 'DAYCARE WORKER']);

        $this->assertSame('LABOR GROUP', $event->fresh()->payroll_category);
    }
}
