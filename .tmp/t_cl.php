<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
auth()->login($user);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$r = $kernel->handle(Illuminate\Http\Request::create('/client-list', 'GET'));
$html = $r->getContent();
echo 'STATUS: ' . $r->getStatusCode() . PHP_EOL;

// every element id the guard requires
$ids = ['clientKeywordInput','clientSexFilter','clientCivilStatusFilter','clientCityFilter',
    'clientBarangayFilter','clientRecordTypeFilter','clientFiltersResetBtn','clientFiltersToggleBtn',
    'clientFiltersBody','clientDateFrom','clientDateTo','clientDateApplyBtn','clientFiltersCountBadge',
    'clientSearchSummary'];
foreach ($ids as $id) {
    if (!str_contains($html, 'id="' . $id . '"')) echo "MISSING ID: $id" . PHP_EOL;
}
echo 'ID GUARD CHECK DONE' . PHP_EOL;

// Andal rows present on page 1?
preg_match_all('/data-search-all="([^"]*)"/', $html, $all);
$hits = array_filter($all[1], fn ($v) => str_contains(strtolower($v), 'andal'));
echo 'ROWS_WITH_ANDAL_IN_SEARCH_ALL: ' . count($hits) . PHP_EOL;
foreach ($hits as $h) { echo '  "' . substr($h, 0, 60) . '..."' . PHP_EOL; }

echo 'TOTAL_ROWS_PAGE1: ' . count($all[1]) . PHP_EOL;
