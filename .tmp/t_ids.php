<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
auth()->login($user);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$r = $kernel->handle(Illuminate\Http\Request::create('/client-list', 'GET'));
$html = $r->getContent();

// duplicate-id check for every element referenced by the main script
$ids = ['clientKeywordInput','clientSexFilter','clientCivilStatusFilter','clientCityFilter',
    'clientBarangayFilter','clientRecordTypeFilter','clientFiltersToggleBtn','clientFiltersFormEl' => 0,
    'clientFiltersForm','clientDateFrom','clientDateTo','clientDateApplyBtn','clientFiltersCountBadge',
    'clientSearchSummary','clientSearchNoResultsRow','clientFiltersResetBtn'];
foreach ($ids as $id => $v) {
    $idStr = is_string($id) ? $id : $v;
    $count = substr_count($html, 'id="' . $idStr . '"');
    if ($count !== 1) {
        echo "ID '{$idStr}' occurrences: {$count}" . PHP_EOL;
    }
}
echo 'DUP CHECK DONE' . PHP_EOL;

// show the small scripts
foreach (glob(__DIR__ . '/script_*.js') as $f) {
    if (basename($f) === 'script_4.js') continue;
    echo '--- ' . basename($f) . ' ---' . PHP_EOL;
    echo substr(file_get_contents($f), 0, 300) . PHP_EOL;
}
