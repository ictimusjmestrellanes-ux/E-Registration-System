<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('transaction_events')->update(['payroll_category' => 'INDIGENT']);

        $this->setCategory(['SOLO PARENTS', 'SOLO PARENT GRADUATES'], 'SOLO PARENT');
        $this->setCategory(['TODA', 'PODA', 'TRANSPORT GROUP'], 'TRANSPORT GROUP');
        $this->setCategory(['DEPRESSED AREA', 'DULONG PADER', 'ISF'], 'URBAN POOR');
        $this->setCategory(['DAYCARE WORKER', 'DELIVERY RIDERS'], 'LABOR GROUP');
    }

    public function down(): void
    {
        DB::table('transaction_events')->update(['payroll_category' => null]);
    }

    /** @param array<int, string> $clientCategories */
    private function setCategory(array $clientCategories, string $payrollCategory): void
    {
        DB::table('transaction_events')
            ->whereIn(DB::raw('UPPER(TRIM(client_category))'), $clientCategories)
            ->update(['payroll_category' => $payrollCategory]);
    }
};
